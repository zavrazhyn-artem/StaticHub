<?php

declare(strict_types=1);

namespace App\Jobs\Gear;

use App\Models\Character;
use App\Services\Gear\EquippedGearSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Reads bnet_equipment from services_raw_data and upserts a GearList of
 * type=current for the character's active spec. Dispatched at the end of
 * RawDataSyncService when fresh equipment data lands.
 */
class SyncCurrentGearListJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 30;
    public int $uniqueFor = 120;

    public function __construct(
        public readonly int $characterId,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->characterId;
    }

    public function handle(EquippedGearSyncService $service): void
    {
        $character = Character::query()->find($this->characterId);
        if (! $character) {
            return;
        }
        $service->syncForCharacter($character);
    }
}
