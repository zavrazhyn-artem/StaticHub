<?php

declare(strict_types=1);

namespace App\Services\Realm;

use App\Models\Realm;
use App\Services\Blizzard\BlizzardGameDataApiService;

class RealmSyncService
{
    public function __construct(
        private readonly BlizzardGameDataApiService $blizzardApiService,
    ) {
    }

    /**
     * Coordinate the realm synchronization process.
     *
     * @return int
     */
    public function executeSync(): int
    {
        $realms = $this->blizzardApiService->getRealms();
        $region = config('services.battlenet.region', 'eu');

        $payload = $this->formatRealmPayload($realms, (string) $region);

        return $this->bulkUpsertRealms($payload);
    }

    /**
     * Format raw realm data from Blizzard API into a database-ready payload.
     *
     * @param array $rawRealms
     * @param string $region
     * @return array
     */
    public function formatRealmPayload(array $rawRealms, string $region): array
    {
        $payload = [];

        foreach ($rawRealms as $realmData) {
            $name = $realmData['name']['en_GB'] ?? $realmData['name']['en_US'] ?? reset($realmData['name']);

            $payload[] = [
                'id' => $realmData['id'],
                'name' => $name,
                'slug' => $realmData['slug'],
                'region' => $region,
            ];
        }

        return $payload;
    }

    /**
     * Bulk upsert realms into the database.
     *
     * @param array $realmsData
     * @return int
     */
    public function bulkUpsertRealms(array $realmsData): int
    {
        Realm::upsert($realmsData, ['id'], ['name', 'slug', 'region']);

        return count($realmsData);
    }
}
