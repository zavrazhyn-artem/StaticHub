<?php

declare(strict_types=1);

namespace App\Services\Wishlist;

use App\Builders\WishlistBuilder;
use App\Enums\StaticGroup\Role;
use App\Exceptions\WishlistImportException;
use App\Jobs\Item\SyncSingleItemMetadataJob;
use App\Models\Character;
use App\Models\GearList;
use App\Models\GearListItem;
use App\Models\Item;
use App\Models\Specialization;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates wishlist imports from external simulators (Raidbots, QE Live).
 * Authorization checks live in the Controller — this service trusts its caller.
 */
final class WishlistService
{
    public function __construct(
        private readonly RaidbotsImportService $raidbots,
        private readonly QELiveImportService $qeLive,
        private readonly WishlistConfigService $configService,
    ) {}

    /**
     * Imports a wishlist from a URL. Auto-detects the source by host, fetches
     * + parses, resolves Character + Specialization, and writes one Wishlist
     * per (raid_slug, difficulty) bucket in the report.
     *
     * @return Collection<int, Wishlist>
     */
    public function importFromUrl(User $user, string $url): Collection
    {
        [$dto, $source] = match (true) {
            $this->raidbots->isOwnUrl($url) => [$this->raidbots->importFromUrl($url), Wishlist::SOURCE_RAIDBOTS],
            $this->qeLive->isOwnUrl($url)   => [$this->qeLive->importFromUrl($url), Wishlist::SOURCE_QE_LIVE],
            default => throw WishlistImportException::unsupportedSource($url),
        };

        $character = $this->resolveCharacter(
            $dto['character']['name'],
            $dto['character']['realm'],
            $user
        );
        $spec = $this->resolveSpec(
            $dto['character']['class_slug'],
            $dto['character']['spec_slug']
        );

        $itemsByRaid = $this->collectItemMetadataByRaid($dto['simulations']);
        $this->upsertItemMetadata($itemsByRaid);
        $this->dispatchMetadataResolveJobs(array_keys($itemsByRaid));

        // Match the import against the static's allowed Droptimizer
        // configs. We resolve "the static" as the first one this
        // character belongs to — single-static is the common case; for
        // a multi-static character the first (lowest-id) match keeps
        // behaviour deterministic. If the character belongs to a static
        // and no allowed config matches, hard-reject the import — that
        // is the whole point of the per-static gate. Characters with no
        // static (e.g. mid-onboarding) are exempt and get matched_config = null.
        //
        // QE Live skips this gate entirely: the "allowed configurations"
        // describe Raidbots-only Droptimizer parameters (fightStyle,
        // numEnemies, fightLength, upgradeEquipped, …) that have no
        // analogue in QE Live's HPS-based upgrade report. Forcing the
        // gate on QE imports always fails on "Upgrade All Equipped
        // required" since that flag literally doesn't exist in the QE
        // payload.
        $staticId = $character->statics()->orderBy('statics.id')->value('statics.id');
        $matchedConfig = null;
        if ($staticId && $source === Wishlist::SOURCE_RAIDBOTS) {
            $matchedConfig = $this->configService->matchForImport((int) $staticId, $dto);
            if ($matchedConfig === null) {
                // Build a per-config breakdown so the player knows which
                // exact field(s) of which config row(s) the report missed.
                $report  = $this->configService->extractReportSummary($dto);
                $configs = $this->configService->listForStatic((int) $staticId);
                $reasons = [];
                foreach ($configs as $c) {
                    $reasons[$c->display_name] = $this->configService->matchFailureReasons($c, $report);
                }
                throw WishlistImportException::noMatchingConfig(
                    $this->configService->summariseReport($dto),
                    $reasons,
                );
            }
        }

        $wishlists = collect();
        foreach ($dto['simulations'] as $sim) {
            $wishlistItems = $this->buildWishlistItemRows($sim['items']);

            $wishlist = Wishlist::query()->upsertReplacing(
                $character,
                $spec,
                $sim['raid_slug'],
                $sim['difficulty'],
                $source,
                $dto['source_url'],
                $dto['source_report_id'],
                null, // raw_payload kept off in Phase 0 — column reserved for future debug flag
                $dto['generated_at'],
                $wishlistItems,
                $matchedConfig?->id,
            );

            $wishlists->push($wishlist);
        }

        return $wishlists;
    }

    /**
     * Look up an existing character. Per-product decision: don't auto-create
     * stub characters from a wishlist URL — fail clearly so the user runs a
     * proper bnet sync first. Also enforce ownership so a user can't import
     * a wishlist for someone else's character.
     */
    private function resolveCharacter(string $name, string $realm, User $user): Character
    {
        $character = Character::query()->findByNameAndRealm($name, $realm);

        if (! $character) {
            throw WishlistImportException::characterNotFound($name, $realm);
        }

        if ($character->user_id !== $user->id) {
            throw WishlistImportException::characterNotOwned($name, $realm);
        }

        return $character;
    }

    private function resolveSpec(string $classSlug, string $specSlug): Specialization
    {
        $className = ucfirst(strtolower($classSlug));
        if ($className === 'Deathknight') {
            $className = 'Death Knight';
        } elseif ($className === 'Demonhunter') {
            $className = 'Demon Hunter';
        }

        $specName = ucfirst(strtolower($specSlug));
        if ($specName === 'Beastmastery') {
            $specName = 'Beast Mastery';
        }

        $spec = Specialization::query()
            ->where('class_name', $className)
            ->where('name', $specName)
            ->first();

        if (! $spec) {
            throw WishlistImportException::specNotFound($className, $specName);
        }

        return $spec;
    }

    /**
     * For each (raid_slug, difficulty, item_id) tuple in the static, build
     * the list of claimants — characters who have that exact item in their
     * wishlist for the same raid/difficulty bucket. Powers the loot-council
     * modal: counter on the card, full breakdown on click.
     *
     * Each claimant carries:
     *  - role           : 'main' | 'alt' (character_static.role pivot)
     *  - is_main_spec   : true if the wishlist's spec is this character's
     *                     main spec in the static — false means it's an
     *                     off-spec wishlist (e.g. Disc on a Holy main)
     *  - is_bis         : the wishlist's own status is "best" for this slot
     *  - value/percent  : sim'd upgrade gain in that wishlist
     *
     * Two queries: one for the static-role pivot, one for the main-spec
     * pivot. Lookup map → no N+1 in the per-item loop.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function buildClaimantsLookup(Collection $wishlists, int $staticId): array
    {
        if ($wishlists->isEmpty()) {
            return [];
        }

        $charIds = $wishlists->pluck('character_id')->unique()->values()->all();

        $charRoles = DB::table('character_static')
            ->whereIn('character_id', $charIds)
            ->where('static_id', $staticId)
            ->pluck('role', 'character_id')
            ->all();

        $mainSpecByChar = DB::table('character_static_specs')
            ->whereIn('character_id', $charIds)
            ->where('static_id', $staticId)
            ->where('is_main', true)
            ->pluck('spec_id', 'character_id')
            ->all();

        // Pre-load "is this item in the character's BiS gear list for
        // this spec" — keyed by "{char}:{spec}:{item}". The wishlist item's
        // own status='b' means "raidbots flagged it as a BiS-tier upgrade",
        // which is NOT what the loot-council cares about: they want to know
        // whose curated BiS list this item appears on. Mirrors the green-
        // highlight fix on the cards.
        $bisListLookup = $this->buildBisListMembershipLookup($charIds);

        // For alt characters, resolve "whose alt" — find the user's main
        // character in the same static. Lets the modal render
        // "Alt of Zavrikk" instead of a bare "Alt" badge.
        $charUserIds = $wishlists->mapWithKeys(fn ($w) => [
            $w->character_id => $w->character?->user_id,
        ])->filter()->all();

        $mainCharByUser = empty($charUserIds)
            ? []
            : DB::table('characters')
                ->join('character_static', 'characters.id', '=', 'character_static.character_id')
                ->whereIn('characters.user_id', array_values(array_unique($charUserIds)))
                ->where('character_static.static_id', $staticId)
                ->where('character_static.role', 'main')
                ->pluck('characters.name', 'characters.user_id')
                ->all();

        $lookup = [];
        foreach ($wishlists as $w) {
            foreach ($w->items as $i) {
                $key = "{$w->raid_slug}|{$w->difficulty}|{$i->item_id}";
                $lookup[$key] ??= [];
                $role = $charRoles[$w->character_id] ?? 'alt';
                $userId = $charUserIds[$w->character_id] ?? null;
                // "Alt of X" only makes sense when the alt's owner has a
                // main character that is itself in this static. Same-named
                // owner-of-self collapses to null so we don't render
                // "Alt of Zaavrik" on Zaavrik's own row.
                $mainName = ($role === 'alt' && $userId !== null)
                    ? ($mainCharByUser[$userId] ?? null)
                    : null;
                if ($mainName === $w->character->name) {
                    $mainName = null;
                }

                $lookup[$key][] = [
                    'character_id'   => $w->character_id,
                    'character_name' => $w->character->name,
                    'playable_class' => $w->character->playable_class,
                    'avatar_url'     => $w->character->avatar_url,
                    'role'           => $role,
                    'main_of'        => $mainName,
                    'spec_id'        => $w->spec_id,
                    'spec_name'      => $w->specialization?->name,
                    'is_main_spec'   => isset($mainSpecByChar[$w->character_id])
                        && (int) $mainSpecByChar[$w->character_id] === (int) $w->spec_id,
                    'is_bis'         => isset($bisListLookup["{$w->character_id}:{$w->spec_id}:{$i->item_id}"]),
                    'value'          => (int) $i->value,
                    'percent'        => (float) $i->percent,
                ];
            }
        }
        return $lookup;
    }

    /**
     * Aggregates item metadata across all simulations so we can upsert
     * unique items in a single pass.
     *
     * @param array<int, array{raid_slug:string, difficulty:string, items:array}> $simulations
     * @return array<int, array{slot:string, source_slug:string, source_type:string, encounter_slug:string}>
     */
    private function collectItemMetadataByRaid(array $simulations): array
    {
        $byItem = [];
        foreach ($simulations as $sim) {
            foreach ($sim['items'] as $item) {
                $byItem[$item['item_id']] ??= [
                    'slot'           => $item['slot'],
                    'source_slug'    => $sim['raid_slug'],
                    'source_type'    => 'raid',
                    'encounter_slug' => 'encounter-' . $item['encounter_id'],
                ];
            }
        }

        return $byItem;
    }

    /**
     * Upserts items so wishlist_items FKs resolve. Names are intentionally left
     * NULL — Blizzard sync pipeline elsewhere fills those in. We only set the
     * gear-management metadata (slot/source/encounter) when missing.
     *
     * @param array<int, array{slot:string, source_slug:string, source_type:string, encounter_slug:string}> $itemsById
     */
    private function upsertItemMetadata(array $itemsById): void
    {
        if (empty($itemsById)) {
            return;
        }

        $now = now();
        $rows = [];
        foreach ($itemsById as $itemId => $meta) {
            $rows[] = [
                'id'             => $itemId,
                'name'           => null,
                'slot'           => $meta['slot'],
                'source_slug'    => $meta['source_slug'],
                'source_type'    => $meta['source_type'],
                'encounter_slug' => $meta['encounter_slug'],
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }

        DB::table('items')->upsert(
            $rows,
            ['id'],
            ['slot', 'source_slug', 'source_type', 'encounter_slug', 'updated_at']
        );
    }

    /**
     * Builds a {character_id:spec_id => {item_id => [{name, tier}, ...]}}
     * lookup by joining gear_lists+gear_list_items in a single query.
     *
     * The `tier` label (e.g. "H 4/4") is set when the entry is from the
     * Current list AND its ilvl maps to a known upgrade track — this lets
     * the wishlist UI show "Listed in: Current (Hero 4/4)" so the user
     * sees they're equipping a lower-tier copy of the wished item.
     *
     * @param Collection<int, Wishlist> $wishlists
     * @return array<string, array<int, array<int, array{name:string, tier:?string}>>>
     */
    private function buildListedInLookup(Collection $wishlists): array
    {
        if ($wishlists->isEmpty()) {
            return [];
        }

        $contexts = $wishlists
            ->map(fn (Wishlist $w) => [$w->character_id, $w->spec_id])
            ->unique(fn ($pair) => "{$pair[0]}:{$pair[1]}");

        $lookup = [];

        foreach ($contexts as [$charId, $specId]) {
            $rows = GearListItem::query()
                ->join('gear_lists', 'gear_list_items.list_id', '=', 'gear_lists.id')
                ->where('gear_lists.character_id', $charId)
                ->where('gear_lists.spec_id', $specId)
                ->select(
                    'gear_list_items.item_id',
                    'gear_list_items.item_level',
                    'gear_list_items.bonus_ids',
                    'gear_lists.name',
                    'gear_lists.type'
                )
                ->get();

            $key = "{$charId}:{$specId}";
            $lookup[$key] = [];
            foreach ($rows as $row) {
                $lookup[$key][$row->item_id] ??= [];
                $lookup[$key][$row->item_id][] = [
                    'name' => $row->name,
                    'type' => $row->type,
                    'tier' => $row->type === 'current'
                        ? $this->trackLabelForRow($row)
                        : null,
                ];
            }
        }

        return $lookup;
    }

    /**
     * Resolve "{TrackInitial} {level}/{max}" for a GearListItem row.
     *
     * Bonus-id resolution is authoritative — that's the bnet truth and
     * unique per upgrade tier. We fall back to ilvl reverse-lookup only
     * when bonus_ids are absent (e.g. legacy data, custom lists). Reverse
     * by ilvl is brittle because Hero and Myth tracks can overlap on
     * boundary ilvls (276 = Hero 6/6 OR Myth 2/6 in some seasons).
     */
    private function trackLabelForRow(object $row): ?string
    {
        $tracks = config('wow_season.item_upgrade_tracks', []);

        $bonusIds = $this->decodeBonusIds($row->bonus_ids ?? null);
        foreach ($bonusIds as $bid) {
            if (isset($tracks[$bid])) {
                return $this->formatTrackLabel($tracks[$bid]);
            }
        }

        // Fallback when bonus_ids empty or none recognized
        $ilvl = (int) ($row->item_level ?? 0);
        if ($ilvl <= 0) {
            return null;
        }
        foreach ($tracks as $info) {
            if ((int) ($info['ilvl'] ?? 0) === $ilvl) {
                return $this->formatTrackLabel($info);
            }
        }
        return null;
    }

    /** @return array<int, int> */
    private function decodeBonusIds(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_map('intval', $raw);
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? array_map('intval', $decoded) : [];
        }
        return [];
    }

    private function formatTrackLabel(array $info): string
    {
        $abbr = substr((string) ($info['track'] ?? ''), 0, 1);
        return $abbr . ' ' . ($info['level'] ?? 0) . '/' . ($info['max'] ?? 0);
    }

    /**
     * Queues a Blizzard API metadata fetch for items that still have NULL name.
     * Already-resolved items are skipped — the existing pipeline + cache handles them.
     */
    private function dispatchMetadataResolveJobs(array $itemIds): void
    {
        if (empty($itemIds)) {
            return;
        }

        $needsResolve = Item::query()
            ->whereIn('id', $itemIds)
            ->whereNull('name')
            ->pluck('id');

        foreach ($needsResolve as $id) {
            SyncSingleItemMetadataJob::dispatch((int) $id);
        }
    }

    /**
     * Dedupes an item bag by item_id keeping the highest-value variant
     * (Raidbots emits multiple rows per item for warforged/socket variants),
     * orders by value DESC, marks top item per slot as BIS, and returns rows
     * shaped for WishlistItem insert.
     *
     * @param array<int, array{item_id:int, slot:string, value:int, percent:float, ...}> $rawItems
     * @return array<int, array{item_id:int, value:int, percent:float, status:string, comment:null, position:int}>
     */
    private function buildWishlistItemRows(array $rawItems): array
    {
        $bestPerItem = [];
        foreach ($rawItems as $row) {
            $key = $row['item_id'];
            if (! isset($bestPerItem[$key]) || $row['value'] > $bestPerItem[$key]['value']) {
                $bestPerItem[$key] = $row;
            }
        }

        usort($bestPerItem, fn ($a, $b) => $b['value'] <=> $a['value']);

        $topPerSlot = [];
        $rows = [];
        $position = 0;
        foreach ($bestPerItem as $row) {
            $slot = $row['slot'];
            $isTopForSlot = ! isset($topPerSlot[$slot]);
            if ($isTopForSlot) {
                $topPerSlot[$slot] = true;
            }

            $rows[] = [
                'item_id'    => $row['item_id'],
                'item_level' => $row['item_level'] ?? null,
                'enchant_id' => ($row['enchant_id'] ?? 0) > 0 ? $row['enchant_id'] : null,
                'value'      => $row['value'],
                'percent'    => $row['percent'],
                'status'     => $isTopForSlot && $row['value'] > 0
                    ? WishlistItem::STATUS_BIS
                    : WishlistItem::STATUS_NOT_BEST,
                'comment'    => null,
                'position'   => $position++,
            ];
        }

        return $rows;
    }

    // -------------------------------------------------------------------------
    // Read API
    // -------------------------------------------------------------------------

    /**
     * Build the payload for the gear-management Vue page for a given static.
     * Returns wishlists grouped by character and raid for easy rendering.
     *
     * Access scope:
     * - leader/officer: see every character's wishlist
     * - member: see only own characters' wishlists. Claimants for those
     *   items still expose the full static pool — that's the whole point
     *   of "who else wants this drop" for a regular player.
     */
    public function buildGearViewPayload(int $staticId, ?int $userId = null, Role $accessRole = Role::Member): array
    {
        $excluded = (array) config('wow_season.wishlist_excluded_raid_slugs', []);

        // Full pool — drives the claimants lookup so members still see
        // every competitor on items they personally wished for. raidsOnly()
        // is the product rule (wishlist UI is raid-only, defensive against
        // legacy non-raid rows).
        $allWishlists = Wishlist::query()
            ->forStatic($staticId)
            ->raidsOnly()
            ->when(! empty($excluded), fn ($q) => $q->whereNotIn('raid_slug', $excluded))
            ->withDisplayRelations()
            ->with('matchedConfig:id,display_name,weight')
            ->orderBy('character_id')
            ->orderBy('raid_slug')
            ->orderBy('difficulty')
            ->get();

        // What the viewer is allowed to render. Members are hard-scoped
        // to their own characters at this layer; the full pool stays
        // available for claimant resolution.
        $visibleWishlists = $accessRole === Role::Member && $userId !== null
            ? $allWishlists->filter(fn ($w) => $w->character?->user_id === $userId)->values()
            : $allWishlists;

        // Pre-load "listed in" lookup: for each (char_id, spec_id) build a map
        // item_id => array of gear list names. Avoids N+1 queries in the loop.
        $listedIn = $this->buildListedInLookup($visibleWishlists);

        // Pre-load boss + encounter metadata for every referenced item id —
        // wishlist UI groups by boss, and each item card shows the boss name
        // alongside the source raid. Single query, keyed for O(1) lookup.
        $itemIds = $visibleWishlists->flatMap(fn ($w) => $w->items->pluck('item_id'))->unique()->values()->all();
        $bossByItemId = empty($itemIds)
            ? []
            : \App\Models\SeasonItem::query()
                ->whereIn('id', $itemIds)
                ->get(['id', 'boss_name', 'encounter_id', 'inventory_type'])
                ->keyBy('id');

        // Pre-build the claimants lookup so the per-item loop can attach
        // {character, role, spec, is_bis, value} entries without N+1 queries.
        // Keyed by (raid_slug, difficulty, item_id) — each card is for that
        // exact tuple, so claimants for one tuple don't bleed into another.
        // Built from the FULL pool so a member sees every competitor.
        $claimantsByItem = $this->buildClaimantsLookup($allWishlists, $staticId);

        // Per-character main spec for THIS static. Drives the "Mains
        // only" filter — a character without any is_main spec in this
        // static is treated as alt and hidden by default; for those that
        // do have a main spec, only that spec's wishlist counts as main.
        $charIds = $visibleWishlists->pluck('character_id')->unique()->all();
        $mainSpecByCharId = $this->buildMainSpecLookup($staticId, $charIds);
        // character_static.role is the authoritative main/alt flag for
        // this static — needed alongside main_spec so "Mains only" can
        // hide alt characters even when they happen to have a main_spec
        // assigned.
        $rolesByCharId = empty($charIds) ? [] : DB::table('character_static')
            ->where('static_id', $staticId)
            ->whereIn('character_id', $charIds)
            ->pluck('role', 'character_id')
            ->all();
        // Owner display name per user — drives the per-player chip strip.
        // Members never see the strip (they only have themselves), but the
        // lookup is cheap so we always build it.
        $ownerUserIds = $visibleWishlists
            ->map(fn ($w) => $w->character?->user_id)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $userNameById = empty($ownerUserIds) ? [] : DB::table('users')
            ->whereIn('id', $ownerUserIds)
            ->pluck('name', 'id')
            ->all();

        $grouped = [];
        foreach ($visibleWishlists as $w) {
            $charKey = $w->character_id;
            $grouped[$charKey] ??= [
                'character' => [
                    'id'             => $w->character->id,
                    'name'           => $w->character->name,
                    'realm'          => $w->character->realm?->slug,
                    'playable_class' => $w->character->playable_class,
                    'avatar_url'     => $w->character->avatar_url,
                    'is_own'         => $userId !== null && $w->character->user_id === $userId,
                    'main_spec_id'   => $mainSpecByCharId[$w->character_id] ?? null,
                    'static_role'    => $rolesByCharId[$w->character_id] ?? null,
                    // Owner — drives the per-player chip strip on the
                    // wishlist tab. Characters belonging to the same user
                    // collapse to a single chip; the chip face is the
                    // user's main character in this static (or any of
                    // their characters as fallback).
                    'owner'          => [
                        'user_id'   => $w->character->user_id,
                        'user_name' => $userNameById[$w->character->user_id] ?? null,
                    ],
                ],
                'wishlists' => [],
            ];

            $grouped[$charKey]['wishlists'][] = [
                'id'              => $w->id,
                'spec_id'         => $w->spec_id,
                'spec_name'       => $w->specialization?->name,
                'raid_slug'       => $w->raid_slug,
                'difficulty'      => $w->difficulty,
                'source'          => $w->source,
                'source_url'      => $w->source_url,
                'generated_at'    => $w->generated_at?->toIso8601String(),
                'imported_at'     => $w->imported_at->toIso8601String(),
                'matched_config'  => $w->matchedConfig
                    ? ['id' => $w->matchedConfig->id, 'name' => $w->matchedConfig->display_name, 'weight' => (float) $w->matchedConfig->weight]
                    : null,
                'items'           => $w->items->map(function (WishlistItem $i) use ($w, $listedIn, $bossByItemId, $claimantsByItem) {
                    $key = $w->character_id . ':' . $w->spec_id;
                    $catalog = $bossByItemId[$i->item_id] ?? null;
                    $claimants = $claimantsByItem["{$w->raid_slug}|{$w->difficulty}|{$i->item_id}"] ?? [];
                    return [
                        'item_id'         => $i->item_id,
                        'item_name'       => $i->item->name,
                        'item_icon'       => $i->item->icon,
                        'item_slot'       => $i->item->slot,
                        'item_level'      => $i->item_level,
                        'enchant_id'      => $i->enchant_id,
                        'value'           => $i->value,
                        'percent'         => $i->percent,
                        'status'          => $i->status,
                        'comment'         => $i->comment,
                        'listed_in'       => $listedIn[$key][$i->item_id] ?? [],
                        'boss_name'       => $catalog?->boss_name,
                        'encounter_id'    => $catalog?->encounter_id,
                        'claimants'       => $claimants,
                        'claimant_count'  => count($claimants),
                        'bis_count'       => count(array_filter($claimants, fn ($c) => $c['is_bis'] ?? false)),
                    ];
                })->values()->all(),
            ];
        }

        return array_values($grouped);
    }

    /**
     * Set of "{character_id}:{spec_id}:{item_id}" keys for items present
     * in the character's BiS gear list for that spec. The lookup loads
     * once per payload build so the per-item claimant loop stays O(1).
     *
     * @param array<int,int> $characterIds
     * @return array<string,true>
     */
    private function buildBisListMembershipLookup(array $characterIds): array
    {
        if (empty($characterIds)) return [];
        $rows = DB::table('gear_lists')
            ->join('gear_list_items', 'gear_lists.id', '=', 'gear_list_items.list_id')
            ->whereIn('gear_lists.character_id', $characterIds)
            ->where('gear_lists.type', 'bis')
            ->select('gear_lists.character_id', 'gear_lists.spec_id', 'gear_list_items.item_id')
            ->get();
        $out = [];
        foreach ($rows as $r) {
            $out["{$r->character_id}:{$r->spec_id}:{$r->item_id}"] = true;
        }
        return $out;
    }

    /**
     * Map character_id => main spec_id within the given static. Pulled
     * from character_static_specs.is_main, which is the same source the
     * roster uses for "this is the spec we're tracking for this person".
     *
     * @param array<int,int> $characterIds
     * @return array<int,int>
     */
    private function buildMainSpecLookup(int $staticId, array $characterIds): array
    {
        if (empty($characterIds)) return [];
        return \DB::table('character_static_specs')
            ->where('static_id', $staticId)
            ->where('is_main', true)
            ->whereIn('character_id', $characterIds)
            ->pluck('spec_id', 'character_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
