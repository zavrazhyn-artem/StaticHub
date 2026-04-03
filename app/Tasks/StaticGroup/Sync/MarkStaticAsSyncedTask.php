<?php

declare(strict_types=1);

namespace App\Tasks\StaticGroup\Sync;

use App\Enums\StaticGroup\SyncType;
use App\Models\StaticGroup;

class MarkStaticAsSyncedTask
{
    /**
     * Update the last sync timestamp for a specific sync type of a static group.
     *
     * @param int $staticId The ID of the static group.
     * @param SyncType $syncType The sync type enum.
     * @return void
     */
    public function run(int $staticId, SyncType $syncType): void
    {
        $syncTypeValue = $syncType->value;
        $syncColumn = "{$syncTypeValue}_last_synced_at";

        StaticGroup::withoutGlobalScopes()
            ->where('id', $staticId)
            ->update([
                $syncColumn => now(),
            ]);
    }
}
