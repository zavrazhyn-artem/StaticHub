<?php

declare(strict_types=1);

namespace App\Tasks\StaticGroup;

use App\Models\StaticGroup;
use App\Services\ConsumableService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateStaticGroupTask
{
    public function __construct(
        private readonly ConsumableService $consumableService
    ) {}

    /**
     * Create a new static group and assign its owner.
     *
     * @param string $name
     * @param string $realmName
     * @param string $realmSlug
     * @param string $region
     * @param int $ownerId
     * @return StaticGroup
     */
    public function run(string $name, string $realmName, string $realmSlug, string $region, int $ownerId): StaticGroup
    {
        return DB::transaction(function () use ($name, $realmName, $realmSlug, $region, $ownerId) {
            $static = StaticGroup::create([
                'name' => $name,
                'slug' => Str::slug($name . '-' . $realmSlug),
                'server' => $realmName,
                'region' => $region,
                'owner_id' => $ownerId,
            ]);

            $calculatedCost = $this->consumableService->buildConsumablesPayload($static)['grand_total_weekly_cost'] ?? 0;
            $costPerPlayer = $calculatedCost / 20;
            // Round up to nearest 1000 gold (10 000 000 copper)
            $roundedTax = (int) (ceil($costPerPlayer / 10000000) * 10000000);
            $static->update(['weekly_tax_per_player' => $roundedTax]);

            $static->assignOwner($ownerId);

            return $static;
        });
    }
}
