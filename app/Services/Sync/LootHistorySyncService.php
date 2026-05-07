<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Models\Character;
use App\Models\LootDistribution;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Receives BlastROutboxEvents from the BlastR Desktop bridge and
 * persists them as loot_distributions rows. Idempotent on event_uuid:
 * any UUID already in the database is reported back as a duplicate
 * and the row is left alone, so the bridge can safely retry on
 * partial failure without creating noise.
 */
final class LootHistorySyncService
{
    /**
     * @param  list<array<string,mixed>>  $events
     * @return array{accepted:int, duplicates:int, rejected:list<array{event_uuid:?string,reason:string}>}
     */
    public function ingest(User $user, array $events): array
    {
        $static = $user->statics()->orderBy('statics.id')->first();
        if ($static === null) {
            return ['accepted' => 0, 'duplicates' => 0, 'rejected' => [
                ['event_uuid' => null, 'reason' => 'user has no static'],
            ]];
        }

        // Pre-fetch existing UUIDs in one query so the per-event loop
        // doesn't roundtrip; cheaper than INSERT IGNORE per row.
        $uuids = array_filter(array_map(fn ($e) => $e['event_uuid'] ?? null, $events));
        $existing = LootDistribution::query()->existingUuids($uuids);
        $existingSet = array_flip($existing);

        // Resolve "Name-Realm" recipient names to character ids in a
        // single query — we only need the slugged side, character names
        // are case-sensitive by Blizzard convention.
        $recipientNames = array_unique(array_filter(array_map(
            fn ($e) => $e['recipient'] ?? null,
            $events,
        )));
        $charIds = $this->resolveCharacterIds($static->id, $recipientNames);

        $accepted = 0;
        $duplicates = 0;
        $rejected = [];
        $now = CarbonImmutable::now();

        DB::beginTransaction();
        try {
            foreach ($events as $event) {
                $uuid = $event['event_uuid'] ?? null;
                if (! is_string($uuid) || $uuid === '') {
                    $rejected[] = ['event_uuid' => null, 'reason' => 'missing event_uuid'];
                    continue;
                }
                if (isset($existingSet[$uuid])) {
                    $duplicates++;
                    continue;
                }

                $awardedAt = $this->parseTimestamp($event['awarded_at'] ?? null);
                if ($awardedAt === null) {
                    $rejected[] = ['event_uuid' => $uuid, 'reason' => 'invalid awarded_at'];
                    continue;
                }
                $raid = is_array($event['raid'] ?? null) ? $event['raid'] : [];
                $item = is_array($event['item'] ?? null) ? $event['item'] : [];

                LootDistribution::query()->create([
                    'event_uuid'             => $uuid,
                    'static_id'              => $static->id,
                    'awarded_at'             => $awardedAt,
                    'raid_slug'              => (string) ($raid['slug'] ?? ''),
                    'raid_difficulty'        => (string) ($raid['difficulty'] ?? ''),
                    'boss_name'              => $raid['boss'] ?? null,
                    'pull_id'                => $raid['pull_id'] ?? null,
                    'recipient_name'         => (string) ($event['recipient'] ?? ''),
                    'recipient_character_id' => $charIds[$event['recipient'] ?? ''] ?? null,
                    'awarded_by_name'        => $event['awarded_by'] ?? null,
                    'item_id'                => (int) ($item['id'] ?? 0),
                    'item_level'             => isset($item['ilvl']) ? (int) $item['ilvl'] : null,
                    'bonus_ids'              => $item['bonus_ids'] ?? null,
                    'enchant_id'             => isset($item['enchant']) ? (int) $item['enchant'] : null,
                    'gem_ids'                => $item['gems'] ?? null,
                    'method'                 => (string) ($event['method'] ?? 'free'),
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
     * @param  list<string>  $names  in "Name-realm-slug" form
     * @return array<string, int>
     */
    private function resolveCharacterIds(int $staticId, array $names): array
    {
        if (empty($names)) {
            return [];
        }

        // Decompose each "Name-realm" into the lookup pair, then resolve
        // in one round-trip via a join with realms. We only consider
        // characters currently rostered in the loot master's static so
        // a stray cross-realm pull doesn't accidentally credit someone.
        $lookups = [];
        foreach ($names as $key) {
            [$name, $realm] = $this->splitCharacterKey($key);
            if ($name === '' || $realm === '') continue;
            $lookups[$key] = ['name' => $name, 'realm' => strtolower($realm)];
        }
        if (empty($lookups)) return [];

        $rows = DB::table('characters')
            ->join('realms', 'characters.realm_id', '=', 'realms.id')
            ->join('character_static', 'characters.id', '=', 'character_static.character_id')
            ->where('character_static.static_id', $staticId)
            ->whereIn(
                DB::raw("CONCAT(characters.name, '-', LOWER(realms.slug))"),
                array_keys($lookups),
            )
            ->get(['characters.id', 'characters.name', 'realms.slug']);

        $out = [];
        foreach ($rows as $r) {
            $out[$r->name . '-' . strtolower($r->slug)] = (int) $r->id;
        }
        return $out;
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
}
