<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Models\StaticGroup;
use App\Models\User;
use App\Models\Wishlist;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Builds the SavedVariables-shaped payload that the BlastR Desktop
 * bridge consumes from GET /api/v1/sync/wishlists.
 *
 * The shape is mirrored exactly in external/_spec/savedvariables.md;
 * see that doc for the contract. When fields here change, bump the
 * BlastRSchema constant and update the addon side in lockstep.
 */
final class WishlistSyncService
{
    /**
     * Bump when the response shape changes in a backwards-incompatible
     * way. Bridge writes this directly into BlastRSchema in the SV file.
     */
    public const SCHEMA_VERSION = 2;

    /**
     * @return array{
     *   schema:int,
     *   team_id:int,
     *   synced_at:string,
     *   characters: array<string, array<string,mixed>>
     * }
     */
    public function buildPayload(User $user, ?CarbonImmutable $since = null): array
    {
        $static = $this->resolveStatic($user);
        if ($static === null) {
            return $this->emptyPayload(0);
        }

        $rosterMeta = $this->loadRosterMeta($static->id);
        $wishlists = Wishlist::query()->syncableForStatic($static->id, $since)->get();

        $characters = [];
        foreach ($wishlists as $w) {
            $charKey = $this->characterKey($w->character?->name, $w->character?->realm?->slug);
            if ($charKey === null) {
                continue;
            }
            $meta = $rosterMeta['characters'][$w->character_id] ?? null;
            if ($meta === null) {
                // Wishlist for a character no longer in this static — skip.
                continue;
            }

            $characters[$charKey] ??= [
                // Uppercase to match the in-game UnitClass() token so
                // the addon can use the value directly for class-color
                // lookups via RAID_CLASS_COLORS.
                'class'           => strtoupper(str_replace([' ', '-'], '', $w->character->playable_class)),
                'user_id'         => $w->character->user_id,
                'role'            => $meta['role'],
                // Latest imported_at across all this character's
                // wishlists. Addon shows it as "updated 2h ago" so the
                // loot master can spot stale wishes at a glance.
                'last_updated_at' => null,
                'specs'           => [],
            ];

            // Track the latest imported_at as we walk the character's
            // wishlists; cheaper than a separate aggregate query.
            $importedIso = $w->imported_at?->toIso8601String();
            if ($importedIso !== null) {
                $current = $characters[$charKey]['last_updated_at'];
                if ($current === null || $importedIso > $current) {
                    $characters[$charKey]['last_updated_at'] = $importedIso;
                }
            }

            $specMeta = $rosterMeta['specs'][$w->character_id][$w->spec_id] ?? null;
            $isMainSpec = $specMeta['is_main'] ?? false;

            $specBlock = &$characters[$charKey]['specs'][(string) $w->spec_id];
            $specBlock ??= [
                'spec_name'    => $w->specialization?->name ?? '',
                'is_main_spec' => $isMainSpec,
                'diff'         => [],
            ];

            $diffKey = $this->normalizeDifficulty($w->difficulty);
            $diffBlock = &$specBlock['diff'][$diffKey];
            $diffBlock ??= ['items' => []];

            $raidName = \App\Models\SeasonItem::SOURCE_DISPLAY_NAMES[$w->raid_slug] ?? $w->raid_slug;
            foreach ($w->items as $item) {
                $diffBlock['items'][(string) $item->item_id] = [
                    'status'    => $item->status,
                    'value'     => (int) $item->value,
                    'percent'   => round((float) $item->percent, 3),
                    'source'    => $w->source ?? '',
                    'boss'      => null,
                    'note'      => $item->comment ?? '',
                    'raid_slug' => $w->raid_slug,
                    'raid_name' => $raidName,
                ];
            }

            unset($specBlock, $diffBlock);
        }

        // Boss attribution comes from season_items; resolve in one pass to
        // avoid an N+1 around the items loop.
        $this->annotateBossNames($characters);

        // Custom GearLists ("M+ keys", "PvP", off-spec, …) for these chars,
        // surfaced as a flat item_id → [list names] map. The addon shows
        // a "List" marker on rows whose item appears in any custom list,
        // with hover tooltip listing the names so the loot master sees
        // why the candidate cares about a non-BiS drop.
        $this->annotateCustomLists($characters);

        // The user's curated Best-in-Slot GearLists (type=bis) drive the
        // addon's ★ marker — NOT the wishlist `status='b'` flag, which
        // is just whatever Raidbots's droptimizer happened to label as
        // top. A real raid lead cares about the player's deliberate
        // pick, not a sim report's automatic flag.
        $this->annotateGearListItems($characters, \App\Models\GearList::TYPE_BIS, 'bis_items');

        // Currently-equipped items (type=current GearList, populated
        // from Battle.net inventory sync) → addon's Eq warning. Lets
        // loot masters see "this player already has this item" at a
        // glance without alt-tabbing to armory.
        $this->annotateGearListItems($characters, \App\Models\GearList::TYPE_CURRENT, 'current_items');

        return [
            'schema'     => self::SCHEMA_VERSION,
            'team_id'    => $static->id,
            'synced_at'  => CarbonImmutable::now('UTC')->toIso8601String(),
            'characters' => $characters,
        ];
    }

    private function resolveStatic(User $user): ?StaticGroup
    {
        // One user belongs to at most one playing static in practice. The
        // pivot allows multi-static (admin tooling), so pick the first
        // deterministically — same pattern the web layer uses for the
        // "current static" middleware default.
        return $user->statics()->orderBy('statics.id')->first();
    }

    /**
     * Resolves per-character role + per-spec is_main flag in two queries
     * so the sync formatter can decorate wishlists without touching the
     * relation lazy loaders.
     *
     * @return array{characters: array<int, array{role:string}>, specs: array<int, array<int, array{is_main:bool}>>}
     */
    private function loadRosterMeta(int $staticId): array
    {
        $charRows = DB::table('character_static')
            ->where('static_id', $staticId)
            ->get(['character_id', 'role']);

        $specRows = DB::table('character_static_specs')
            ->where('static_id', $staticId)
            ->get(['character_id', 'spec_id', 'is_main']);

        $characters = [];
        foreach ($charRows as $r) {
            $characters[(int) $r->character_id] = ['role' => $r->role];
        }

        $specs = [];
        foreach ($specRows as $r) {
            $specs[(int) $r->character_id][(int) $r->spec_id] = [
                'is_main' => (bool) $r->is_main,
            ];
        }

        return ['characters' => $characters, 'specs' => $specs];
    }

    /**
     * Mutates $characters in place, attaching a boss name to every
     * raid-source item that the season_items catalogue can resolve.
     *
     * @param array<string, array<string, mixed>> $characters
     */
    private function annotateBossNames(array &$characters): void
    {
        $itemIds = [];
        foreach ($characters as $char) {
            foreach ($char['specs'] as $spec) {
                foreach ($spec['diff'] as $diff) {
                    foreach (array_keys($diff['items']) as $itemId) {
                        $itemIds[(int) $itemId] = true;
                    }
                }
            }
        }
        if (empty($itemIds)) {
            return;
        }

        $bosses = DB::table('season_items')
            ->whereIn('id', array_keys($itemIds))
            ->where('source_type', 'raid')
            ->pluck('boss_name', 'id')
            ->all();

        foreach ($characters as $charKey => &$char) {
            foreach ($char['specs'] as $specKey => &$spec) {
                foreach ($spec['diff'] as $diffKey => &$diff) {
                    foreach ($diff['items'] as $itemId => &$item) {
                        if (isset($bosses[(int) $itemId])) {
                            $item['boss'] = $bosses[(int) $itemId];
                        }
                    }
                }
            }
        }
    }

    /**
     * Attach character.list_items[itemId] = [list_name, …] for every
     * character in the payload, populated from the user's custom
     * GearLists (type=custom). The addon uses this to render a "List"
     * marker on voting-frame rows whose item lives in any custom list,
     * with the hover tooltip naming each list ("M+ keys", "PvP", …).
     *
     * @param array<string, array<string, mixed>> $characters
     */
    private function annotateCustomLists(array &$characters): void
    {
        // Reverse-resolve charKey → character_id so we can look back from
        // the GearList rows we'll fetch below.
        $charIdByKey = [];
        foreach ($characters as $charKey => $_char) {
            // The key is "Name-realm-slug"; the wishlist loop already
            // persisted user_id but not character_id, so query by name+
            // realm-slug. Easier: re-walk the wishlist rows we already
            // have. Cheaper: read via DB once.
        }

        // Pull list ids + names + character_id in one query, then
        // pull list items separately. Two queries total regardless of
        // list count.
        $rosterCharIds = DB::table('character_static')
            ->whereIn('character_id', function ($q) use ($characters) {
                $q->select('id')->from('characters')
                    ->whereIn(DB::raw("CONCAT(name, '-', (select slug from realms where realms.id = characters.realm_id))"),
                              array_keys($characters));
            })
            ->pluck('character_id')
            ->all();

        if (empty($rosterCharIds)) {
            return;
        }

        $lists = DB::table('gear_lists')
            ->whereIn('character_id', $rosterCharIds)
            ->where('type', \App\Models\GearList::TYPE_CUSTOM)
            ->get(['id', 'character_id', 'name'])
            ->keyBy('id');

        if ($lists->isEmpty()) {
            return;
        }

        $items = DB::table('gear_list_items')
            ->whereIn('list_id', $lists->keys())
            ->get(['list_id', 'item_id']);

        // character_id → key lookup so we can attribute lists back to
        // the right characters[$key] entry without re-doing the slug
        // dance.
        $keyByCharId = DB::table('characters')
            ->join('realms', 'characters.realm_id', '=', 'realms.id')
            ->whereIn('characters.id', $rosterCharIds)
            ->selectRaw("characters.id, CONCAT(characters.name, '-', realms.slug) AS k")
            ->pluck('k', 'id')
            ->all();

        foreach ($items as $row) {
            $list = $lists[$row->list_id] ?? null;
            if (! $list) {
                continue;
            }
            $key = $keyByCharId[$list->character_id] ?? null;
            if ($key === null || ! isset($characters[$key])) {
                continue;
            }
            $itemKey = (string) $row->item_id;
            $characters[$key]['list_items'][$itemKey] ??= [];
            // Dedupe — same list referenced multiple times for the same
            // item (rare, but possible if the user staples a slot more
            // than once) shouldn't show up twice in the tooltip.
            if (! in_array($list->name, $characters[$key]['list_items'][$itemKey], true)) {
                $characters[$key]['list_items'][$itemKey][] = $list->name;
            }
        }
    }

    /**
     * Attach character.<field>[itemId] = true sourced from a singleton
     * GearList of the given type (bis | current). Driven separately
     * from the wishlist data so the addon can render distinct markers
     * for "in candidate's curated BiS" vs "candidate already has this
     * equipped".
     *
     * @param array<string, array<string, mixed>> $characters
     */
    private function annotateGearListItems(array &$characters, string $type, string $field): void
    {
        $charIdRows = DB::table('characters')
            ->join('realms', 'characters.realm_id', '=', 'realms.id')
            ->whereIn(DB::raw("CONCAT(characters.name, '-', realms.slug)"), array_keys($characters))
            ->selectRaw("characters.id, CONCAT(characters.name, '-', realms.slug) AS k")
            ->get();
        if ($charIdRows->isEmpty()) {
            return;
        }
        $keyByCharId = $charIdRows->pluck('k', 'id')->all();

        $lists = DB::table('gear_lists')
            ->whereIn('character_id', array_keys($keyByCharId))
            ->where('type', $type)
            ->get(['id', 'character_id']);
        if ($lists->isEmpty()) {
            return;
        }

        $listOwner = $lists->pluck('character_id', 'id')->all();
        $items = DB::table('gear_list_items')
            ->whereIn('list_id', array_keys($listOwner))
            ->get(['list_id', 'item_id']);

        foreach ($items as $row) {
            $charId = $listOwner[$row->list_id] ?? null;
            $key = $charId !== null ? ($keyByCharId[$charId] ?? null) : null;
            if ($key === null || ! isset($characters[$key])) {
                continue;
            }
            $characters[$key][$field][(string) $row->item_id] = true;
        }
    }

    /**
     * Build the canonical "Name-RealmSlug" key the addon uses to look up
     * a character. Realm slug is lowercased per Blizzard convention.
     */
    private function characterKey(?string $name, ?string $realmSlug): ?string
    {
        if (! $name || ! $realmSlug) {
            return null;
        }
        return $name . '-' . strtolower($realmSlug);
    }

    /**
     * Normalize Blizzard's difficulty strings into the single-letter
     * codes the addon expects. Unknown strings pass through unchanged so
     * test fixtures don't silently lose data.
     */
    private function normalizeDifficulty(string $difficulty): string
    {
        return match (strtolower($difficulty)) {
            'mythic', 'm', 'mythic raid' => 'M',
            'heroic', 'h'                => 'H',
            'normal', 'n'                => 'N',
            'lfr', 'r', 'raid finder'    => 'R',
            default                      => $difficulty,
        };
    }

    private function emptyPayload(int $teamId): array
    {
        return [
            'schema'     => self::SCHEMA_VERSION,
            'team_id'    => $teamId,
            'synced_at'  => CarbonImmutable::now('UTC')->toIso8601String(),
            'characters' => (object) [],
        ];
    }
}
