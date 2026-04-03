<?php

namespace App\Jobs;

use App\Models\StaticGroup;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use App\Services\Discord\DiscordWebhookService;

class SyncStaticGroupJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    /**
     * The number of seconds after which the job's unique lock will be released.
     *
     * @var int
     */
    public $uniqueFor = 3600;

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return (string) $this->staticGroup->id;
    }

    /**
     * Create a new job instance.
     */
    public function __construct(protected StaticGroup $staticGroup)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(DiscordWebhookService $discordService): void
    {
        Log::info("Starting background sync for Static Group: {$this->staticGroup->name}");

        $characters = $this->staticGroup->characters;

        $syncBnet = is_null($this->staticGroup->bnet_last_synced_at) || $this->staticGroup->bnet_last_synced_at->isBefore(now()->subHour());
        $syncRio = is_null($this->staticGroup->rio_last_synced_at) || $this->staticGroup->rio_last_synced_at->isBefore(now()->subHour());
        $syncWcl = is_null($this->staticGroup->wcl_last_synced_at) || $this->staticGroup->wcl_last_synced_at->isBefore(now()->subHour());

        if (!$syncBnet && !$syncRio && !$syncWcl) {
            Log::info("Static Group: {$this->staticGroup->name} is already up to date.");
            return;
        }

        $missingEnchantsCount = 0;
        foreach ($characters as $character) {
            if ($syncBnet) {
                SyncBnetDataJob::dispatch($character);
                $missingEnchantsCount += $this->calculateMissingEnchants($character);
            }
            if ($syncRio) {
                SyncRioDataJob::dispatch($character);
            }
            if ($syncWcl) {
                SyncWclDataJob::dispatch($character);
            }
        }

        // Update the sync timestamps
        $updateData = [];
        if ($syncBnet) $updateData['bnet_last_synced_at'] = now();
        if ($syncRio) $updateData['rio_last_synced_at'] = now();
        if ($syncWcl) $updateData['wcl_last_synced_at'] = now();

        if (!empty($updateData)) {
            $this->staticGroup->update($updateData);

            // Send Discord Sync Report
            $discordService->sendSyncReport($this->staticGroup, [
                'members_updated' => $characters->count() . '/' . $characters->count(),
                'missing_enchants' => $missingEnchantsCount > 0
                    ? $missingEnchantsCount . ' players need attention'
                    : 'All players are fully enchanted!'
            ]);
        }
    }

    /**
     * Basic server-side missing enchants calculation.
     */
    protected function calculateMissingEnchants($character): int
    {
        $data = $character->raw_bnet_data;
        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        $equippedItems = $data['equipped_items'] ?? $data['equipment']['equipped_items'] ?? null;
        if (!$equippedItems) return 0;

        $enchantableSlots = ['BACK', 'CHEST', 'WRIST', 'LEGS', 'FEET', 'FINGER_1', 'FINGER_2', 'MAIN_HAND', 'OFF_HAND'];
        $missing = 0;

        foreach ($equippedItems as $item) {
            $slotType = $item['slot']['type'] ?? '';
            if (in_array($slotType, $enchantableSlots)) {
                if ($slotType === 'OFF_HAND' && ($item['inventory_type']['type'] ?? '') !== 'WEAPON') {
                    continue;
                }
                if (empty($item['enchantments'])) {
                    $missing = 1; // Count character as needing attention if at least one item is missing enchant
                    break;
                }
            }
        }

        return $missing;
    }
}
