<?php

declare(strict_types=1);

namespace App\Services\Character;

use App\Exceptions\JsonSchemaValidationException;
use App\Models\Character;
use App\Models\ServiceRawData;
use App\Services\Blizzard\BlizzardCharacterApiService;
use App\Services\JsonSchemaValidatorService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class RawDataSyncService
{
    private const RIO_BASE_URL = 'https://raider.io/api/v1/characters/profile';
    private const RIO_FIELDS = 'mythic_plus_scores_by_season:current,mythic_plus_ranks,mythic_plus_recent_runs,mythic_plus_best_runs,mythic_plus_weekly_highest_level_runs,gear,talents,raid_progression,guild';

    public function __construct(
        private readonly BlizzardCharacterApiService $blizzardApiService,
        private readonly JsonSchemaValidatorService $schemaValidator,
    ) {}

    /**
     * Syncs raw API data for the given character.
     *
     * @param string $service 'bnet', 'rio', or 'all'
     */
    public function syncRawData(Character $character, string $service = 'all'): void
    {
        $realmSlug = strtolower((string) ($character->realm?->slug ?? ''));
        $name      = strtolower($character->name);
        $region    = strtolower((string) ($character->realm?->region ?? config('services.battlenet.region', 'eu')));

        $updates = [];

        if ($service === 'bnet' || $service === 'all') {
            $this->tryFetch('bnet_profile', 'bnet_profile', $character, $updates, fn() => $this->blizzardApiService->getCharacterProfileSummary($realmSlug, $name));
            $this->tryFetch('bnet_equipment', 'bnet_equipment', $character, $updates, fn() => $this->blizzardApiService->getCharacterEquipment($region, $realmSlug, $name));
            $this->tryFetch('bnet_media', 'bnet_media', $character, $updates, fn() => $this->blizzardApiService->getCharacterMedia($realmSlug, $name));
            $this->tryFetch('bnet_mplus', 'bnet_mplus', $character, $updates, fn() => $this->blizzardApiService->getCharacterMythicKeystoneProfile($realmSlug, $name));
            $this->tryFetch('bnet_raid', 'bnet_raid', $character, $updates, fn() => $this->blizzardApiService->getCharacterRaidEncounters($realmSlug, $name));
            $this->tryFetchNoSchema('bnet_achievement_statistics', $character, $updates, fn() => $this->blizzardApiService->getCharacterAchievementStatistics($realmSlug, $name));
            $this->tryFetchNoSchema('bnet_completed_quests', $character, $updates, fn() => $this->blizzardApiService->getCharacterCompletedQuests($realmSlug, $name));
            $this->tryFetchNoSchema('bnet_pvp_summary', $character, $updates, fn() => $this->blizzardApiService->getCharacterPvpSummary($realmSlug, $name));
            $this->tryFetchNoSchema('bnet_reputations', $character, $updates, fn() => $this->blizzardApiService->getCharacterReputations($realmSlug, $name));
            $this->tryFetchNoSchema('bnet_titles', $character, $updates, fn() => $this->blizzardApiService->getCharacterTitles($realmSlug, $name));
            $this->tryFetchNoSchema('bnet_mounts', $character, $updates, fn() => $this->blizzardApiService->getCharacterMounts($realmSlug, $name));
            $this->tryFetchNoSchema('bnet_pets', $character, $updates, fn() => $this->blizzardApiService->getCharacterPets($realmSlug, $name));
        }

        if ($service === 'rio' || $service === 'all') {
            $this->tryFetch('rio_profile', 'rio_profile', $character, $updates, fn() => $this->fetchRioProfile($region, $realmSlug, $name));
        }

        if ($updates !== []) {
            $rawData = ServiceRawData::updateOrCreate(
                ['character_id' => $character->id],
                $updates,
            );

            if (isset($updates['bnet_achievement_statistics'])) {
                $this->updateVaultSnapshot($rawData, $updates['bnet_achievement_statistics']);
            }

            Log::info('RawDataSyncService: persisted raw data.', [
                'character_id' => $character->id,
                'service' => $service,
                'routes' => array_keys($updates),
            ]);
        } else {
            Log::warning('RawDataSyncService: no valid data to persist.', [
                'character_id' => $character->id,
                'service' => $service,
            ]);
        }
    }

    private function updateVaultSnapshot(ServiceRawData $rawData, array $achStats): void
    {
        $periodKey = $this->currentWowPeriodKey();
        $existingSnap = $rawData->vault_weekly_snapshot ?? [];

        if (isset($existingSnap[$periodKey])) {
            return;
        }

        $delveTierStatIds = config('wow_season.delve_tier_stat_ids', []);
        $delveTotalStatId = (int) config('wow_season.delve_total_stat_id', 0);

        $delveCategory = null;
        foreach ($achStats['categories'] ?? [] as $category) {
            if (($category['name'] ?? '') === 'Delves') {
                $delveCategory = $category;
                break;
            }
        }

        if ($delveCategory === null) {
            return;
        }

        $statsById = [];
        foreach ($delveCategory['statistics'] ?? [] as $stat) {
            $statsById[(int) $stat['id']] = (int) ($stat['quantity'] ?? 0);
        }

        $snapshot = ['total' => $statsById[$delveTotalStatId] ?? 0];
        foreach ($delveTierStatIds as $tier => $statId) {
            $snapshot["tier_{$tier}"] = $statsById[$statId] ?? 0;
        }

        $existingSnap[$periodKey] = $snapshot;

        $eightWeeksAgo = strtotime('-8 weeks');
        foreach (array_keys($existingSnap) as $key) {
            $keyTimestamp = strtotime($key);
            if ($keyTimestamp !== false && $keyTimestamp < $eightWeeksAgo) {
                unset($existingSnap[$key]);
            }
        }

        $rawData->update(['vault_weekly_snapshot' => $existingSnap]);
    }

    private function currentWowPeriodKey(): string
    {
        $now = time();
        $resetTimestamp = strtotime('last wednesday 04:00 UTC', $now);
        if ($resetTimestamp > $now) {
            $resetTimestamp = strtotime('-7 days', $resetTimestamp);
        }
        return gmdate('o-\WW', $resetTimestamp);
    }

    private function tryFetchNoSchema(string $column, Character $character, array &$updates, callable $fetcher): void
    {
        try {
            $payload = $fetcher();
            if ($payload === null || $payload === []) {
                return;
            }
            $updates[$column] = $payload;
        } catch (Throwable $e) {
            Log::error("RawDataSyncService: unexpected error on column '{$column}'.", [
                'character_id' => $character->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function tryFetch(string $route, string $schema, Character $character, array &$updates, callable $fetcher): void
    {
        try {
            $payload = $fetcher();
            if ($payload === null || $payload === []) {
                Log::warning("RawDataSyncService: empty response for route '{$route}'.", ['character_id' => $character->id]);
                return;
            }
            $this->schemaValidator->validate($payload, $schema);
            $updates[$route] = $payload;
        } catch (JsonSchemaValidationException $e) {
            Log::error("RawDataSyncService: schema validation failed for route '{$route}'.", [
                'character_id' => $character->id,
                'schema' => $schema,
                'errors' => $e->getErrorMessages(),
            ]);
        } catch (Throwable $e) {
            Log::error("RawDataSyncService: unexpected error on route '{$route}'.", [
                'character_id' => $character->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function fetchRioProfile(string $region, string $realm, string $name): ?array
    {
        $response = Http::get(self::RIO_BASE_URL, [
            'region' => $region,
            'realm' => $realm,
            'name' => $name,
            'fields' => self::RIO_FIELDS,
        ]);

        if ($response->failed()) {
            Log::warning('RawDataSyncService: Raider.io request failed.', [
                'status' => $response->status(),
                'character' => $name,
                'realm' => $realm,
                'region' => $region,
            ]);
            return null;
        }

        return $response->json();
    }
}
