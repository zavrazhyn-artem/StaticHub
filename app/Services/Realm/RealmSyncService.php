<?php

declare(strict_types=1);

namespace App\Services\Realm;

use App\Services\BlizzardApiService;
use App\Tasks\Realm\BulkUpsertRealmsTask;
use App\Tasks\Realm\FormatRealmPayloadTask;

class RealmSyncService
{
    public function __construct(
        private readonly BlizzardApiService $blizzardApiService,
        private readonly FormatRealmPayloadTask $formatRealmPayloadTask,
        private readonly BulkUpsertRealmsTask $bulkUpsertRealmsTask,
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

        $payload = $this->formatRealmPayloadTask->run($realms, (string) $region);

        return $this->bulkUpsertRealmsTask->run($payload);
    }
}
