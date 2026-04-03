<?php

declare(strict_types=1);

namespace App\Tasks\StaticGroup\Sync;

use App\Enums\StaticGroup\SyncType;
use App\Models\StaticGroup;
use App\Helpers\SyncIntervalHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class FetchStaticsDueForSyncTask
{
    /**
     * Fetch static groups that are due for a specific sync type.
     *
     * @param SyncType $syncType The sync type enum.
     * @return Collection<int, StaticGroup>
     */
    public function run(SyncType $syncType): Collection
    {
        $syncTypeValue = $syncType->value;
        $syncColumn = "{$syncTypeValue}_last_synced_at";

        // Fetch all statics with their characters (to avoid N+1) and filter them based on their tier and last sync time.
        // As per instructions, we use $static->plan_tier or default to 'free'.
        return StaticGroup::withoutGlobalScopes()->with('characters')->get()->filter(function (StaticGroup $static) use ($syncType, $syncColumn) {
            $tier = $static->plan_tier ?? 'free';
            $interval = SyncIntervalHelper::getIntervalInMinutes($tier, $syncType);
            $lastSyncAt = $static->$syncColumn;

            if ($lastSyncAt === null) {
                return true;
            }

            // Ensure $lastSyncAt is a Carbon instance (Eloquent should handle this if cast correctly)
            $lastSyncAt = $lastSyncAt instanceof Carbon ? $lastSyncAt : Carbon::parse($lastSyncAt);

            return $lastSyncAt->isBefore(now()->subMinutes($interval));
        });
    }
}
