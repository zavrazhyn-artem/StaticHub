<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Models\LootDistribution;
use App\Models\SeasonItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Receives BlastROutboxEvents from the BlastR Desktop bridge and persists
 * them as loot_distributions rows. Idempotent on `external_id` (RC's own
 * `entry.id`, an epoch-counter the master looter stamps at award time).
 *
 * Bridge writes a fresh full-scan of `RCLootCouncilLootDB` every push,
 * so duplicate ids are expected and silently counted; only first-seen
 * ids actually insert. Bad rows surface in `rejected[]` so the bridge
 * can decide whether to drop or re-try the offending entry.
 */
final class LootHistorySyncService
{
    /**
     * @param  list<array<string,mixed>>  $events
     * @return array{accepted:int, duplicates:int, rejected:list<array{external_id:?string,reason:string}>}
     */
    public function ingest(User $user, array $events): array
    {
        Log::info('LootHistorySync.ingest', [
            'user_id'     => $user->id,
            'event_count' => count($events),
            'sample'      => array_map(fn ($e) => [
                'external_id' => $e['external_id'] ?? null,
                'item_id'     => $e['item']['id'] ?? null,
                'recipient'   => $e['recipient'] ?? null,
            ], array_slice($events, 0, 3)),
        ]);

        $static = $user->statics()->orderBy('statics.id')->first();
        if ($static === null) {
            return ['accepted' => 0, 'duplicates' => 0, 'rejected' => [
                ['external_id' => null, 'reason' => 'user has no static'],
            ]];
        }

        $externalIds = array_filter(array_map(
            fn ($e) => isset($e['external_id']) ? (string) $e['external_id'] : null,
            $events,
        ));
        $existingSet = array_flip(LootDistribution::query()->existingExternalIds($externalIds));

        $recipientNames = array_unique(array_filter(array_map(
            fn ($e) => $e['recipient'] ?? null,
            $events,
        )));
        $charIds = $this->resolveCharacterIds($static->id, $recipientNames);

        // Batch slot resolution — one query per push regardless of event
        // count. Items missing from season_items (off-season drops) just
        // get a null slot rather than blocking ingest.
        $itemIds = array_unique(array_filter(array_map(
            fn ($e) => isset($e['item']['id']) ? (int) $e['item']['id'] : null,
            $events,
        )));
        $slotByItem = $this->resolveItemSlots($itemIds);
        $baseIlvlByItem = $this->resolveBaseItemLevels($itemIds);

        // Encounter allow-list — real (non-test) awards must reference an
        // encounter that's in the season catalogue, otherwise the row is
        // dropped silently. Test-mode rows bypass this check so /blastr
        // test always lands in the UI no matter where the player was.
        $allowedEncounters = SeasonItem::query()
            ->whereNotNull('encounter_id')
            ->where('season_slug', SeasonItem::CURRENT_SEASON_SLUG)
            ->pluck('encounter_id')
            ->unique()
            ->all();
        $encounterAllowSet = array_flip($allowedEncounters);

        $accepted = 0;
        $duplicates = 0;
        $rejected = [];
        $now = CarbonImmutable::now();

        DB::beginTransaction();
        try {
            foreach ($events as $event) {
                $externalId = isset($event['external_id']) ? (string) $event['external_id'] : '';
                if ($externalId === '') {
                    $rejected[] = ['external_id' => null, 'reason' => 'missing external_id'];
                    continue;
                }
                if (isset($existingSet[$externalId])) {
                    $duplicates++;
                    continue;
                }

                $awardedAt = $this->parseTimestamp($event['awarded_at'] ?? null);
                if ($awardedAt === null) {
                    $rejected[] = ['external_id' => $externalId, 'reason' => 'invalid awarded_at'];
                    continue;
                }
                $raid = is_array($event['raid'] ?? null) ? $event['raid'] : [];
                $item = is_array($event['item'] ?? null) ? $event['item'] : [];
                $resp = is_array($event['response'] ?? null) ? $event['response'] : [];
                $itemId = (int) ($item['id'] ?? 0);
                $isTestMode = (bool) ($event['is_test_mode'] ?? false);
                $encounterId = isset($raid['encounter_id']) ? (int) $raid['encounter_id'] : null;

                // Enforce season allow-list for real awards. Test rows
                // bypass this so the UI shows them even when the player
                // is sitting in Stormwind running /blastr test.
                if (! $isTestMode) {
                    if (! $encounterId || ! isset($encounterAllowSet[$encounterId])) {
                        $rejected[] = ['external_id' => $externalId, 'reason' => 'encounter not in season catalogue'];
                        continue;
                    }
                }

                // Item-level fallback: in /blastr test runs RC mocks the
                // award without bonus_ids, which leaves the link's
                // effective ilvl at the bare base item's value (often
                // ~44 for new-content items). For test rows we substitute
                // the season catalogue's base_item_level so the loot
                // history table doesn't show nonsense numbers. Real raid
                // drops carry full bonus_ids and resolve to a sensible
                // ilvl on the addon side already, so this only kicks in
                // when the addon-reported value is implausibly low.
                $reportedIlvl = isset($item['ilvl']) ? (int) $item['ilvl'] : null;
                $finalIlvl = $reportedIlvl;
                if (($finalIlvl === null || $finalIlvl < 100) && isset($baseIlvlByItem[$itemId])) {
                    $finalIlvl = $baseIlvlByItem[$itemId];
                }

                LootDistribution::query()->create([
                    'external_id'            => $externalId,
                    'static_id'              => $static->id,
                    'awarded_at'             => $awardedAt,
                    'raid_difficulty'        => (string) ($raid['difficulty'] ?? ''),
                    'encounter_id'           => $encounterId,
                    'pull_id'                => $raid['pull_id'] ?? null,
                    'recipient_name'         => (string) ($event['recipient'] ?? ''),
                    'recipient_character_id' => $charIds[$event['recipient'] ?? ''] ?? null,
                    'recipient_spec_id'      => isset($event['recipient_spec_id']) ? (int) $event['recipient_spec_id'] : null,
                    'awarded_by_name'        => $event['awarded_by'] ?? null,
                    'item_id'                => $itemId,
                    'item_slot'              => $slotByItem[$itemId] ?? null,
                    'item_level'             => $finalIlvl,
                    'bonus_ids'              => $item['bonus_ids'] ?? null,
                    'enchant_id'             => isset($item['enchant']) ? (int) $item['enchant'] : null,
                    'gem_ids'                => $item['gems'] ?? null,
                    'method'                 => (string) ($event['method'] ?? 'free'),
                    'response_text'          => isset($resp['text']) ? (string) $resp['text'] : null,
                    'response_color'         => isset($resp['color']) ? $this->normaliseHexColor((string) $resp['color']) : null,
                    'council_same_vote'      => isset($event['council_same_vote']) ? (int) $event['council_same_vote'] : null,
                    'is_award_reason'        => (bool) ($event['is_award_reason'] ?? false),
                    'is_test_mode'           => $isTestMode,
                    'note'                   => $event['note'] ?? null,
                    'received_at'            => $now,
                ]);
                $accepted++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return ['accepted' => $accepted, 'duplicates' => $duplicates, 'rejected' => $rejected];
    }

    /**
     * @param  list<string>  $names  in "Name-Realm" form, where Realm may
     *                                arrive as either CamelCase ("TarrenMill"
     *                                — RC's GetUnitName), space-separated
     *                                ("Tarren Mill"), or dash-slug
     *                                ("tarren-mill" — Blizzard slug).
     *                                We normalise both sides to a single
     *                                canonical form to compare them.
     * @return array<string, int>     keyed by the original input form so
     *                                callers can look up by what they sent.
     */
    private function resolveCharacterIds(int $staticId, array $names): array
    {
        if (empty($names)) {
            return [];
        }

        // Canonical realm form: strip every separator, mb_lowercase. This
        // collapses "TarrenMill", "Tarren Mill", "tarren-mill" all to
        // "tarrenmill" — and leaves cyrillic / unicode realm names alone
        // so we don't mis-match servers with non-ASCII slugs.
        $canon = function (string $s): string {
            return mb_strtolower(preg_replace('/[\s\-_\'\.]+/u', '', $s) ?? '', 'UTF-8');
        };

        $compoundToOriginal = []; // "name|canonRealm" => original key
        foreach ($names as $key) {
            [$name, $realm] = $this->splitCharacterKey($key);
            if ($name === '' || $realm === '') continue;
            $compound = mb_strtolower($name, 'UTF-8') . '|' . $canon($realm);
            $compoundToOriginal[$compound] = $key;
        }
        if (empty($compoundToOriginal)) return [];

        // Resolve any character in this static's roster — main OR alt.
        // We canonicalise the DB side via REPLACE() chain so the IN-list
        // match works regardless of which separator style the addon sent.
        $rows = DB::table('characters')
            ->join('realms', 'characters.realm_id', '=', 'realms.id')
            ->join('character_static', 'characters.id', '=', 'character_static.character_id')
            ->where('character_static.static_id', $staticId)
            ->whereIn(
                DB::raw("LOWER(CONCAT(characters.name, '|', REPLACE(REPLACE(REPLACE(REPLACE(realms.slug, '-', ''), ' ', ''), '_', ''), '''', '')))"),
                array_keys($compoundToOriginal),
            )
            ->get(['characters.id', 'characters.name', 'realms.slug']);

        $out = [];
        foreach ($rows as $r) {
            $compound = mb_strtolower($r->name, 'UTF-8') . '|' . $canon($r->slug);
            $original = $compoundToOriginal[$compound] ?? null;
            if ($original !== null) {
                $out[$original] = (int) $r->id;
            }
        }
        return $out;
    }

    /**
     * @param  list<int>  $itemIds
     * @return array<int, string>
     */
    private function resolveItemSlots(array $itemIds): array
    {
        if (empty($itemIds)) return [];
        return SeasonItem::query()->inventoryTypesByItemIds($itemIds);
    }

    /**
     * Catalog item_id → base ilvl, used as the test-row fallback when
     * the in-game link lacks bonus_ids. Season raid items typically
     * have base_item_level = base mythic raid ilvl, so this gives a
     * plausible number even without a real drop in history.
     *
     * @param  list<int>  $itemIds
     * @return array<int, int>
     */
    private function resolveBaseItemLevels(array $itemIds): array
    {
        if (empty($itemIds)) return [];
        return SeasonItem::query()
            ->whereIn('id', $itemIds)
            ->whereNotNull('base_item_level')
            ->pluck('base_item_level', 'id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    private function splitCharacterKey(string $key): array
    {
        $pos = strpos($key, '-');
        if ($pos === false) return ['', ''];
        return [substr($key, 0, $pos), substr($key, $pos + 1)];
    }

    private function parseTimestamp(?string $raw): ?CarbonImmutable
    {
        if (! is_string($raw) || $raw === '') return null;
        try {
            return CarbonImmutable::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Accept "#rrggbb" or "rrggbb" or "r,g,b" (0-255 ints) and normalise
     * to "#rrggbb". Returns null on garbage so the column stays nullable.
     */
    private function normaliseHexColor(string $raw): ?string
    {
        $s = trim($raw);
        if ($s === '') return null;

        if (preg_match('/^#?([0-9a-fA-F]{6})$/', $s, $m)) {
            return '#' . strtolower($m[1]);
        }
        if (preg_match('/^(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})$/', $s, $m)) {
            return sprintf('#%02x%02x%02x',
                min(255, (int) $m[1]),
                min(255, (int) $m[2]),
                min(255, (int) $m[3]),
            );
        }
        return null;
    }
}
