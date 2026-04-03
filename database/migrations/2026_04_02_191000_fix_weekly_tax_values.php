<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Services\ConsumableService;
use App\Models\StaticGroup;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For each static group where weekly_tax_per_player is 0, calculate and set the default.
        // We use the same logic as in CreateStaticGroupTask but fixed for units.

        $consumableService = app(ConsumableService::class);

        StaticGroup::all()->each(function (StaticGroup $static) use ($consumableService) {
            try {
                $payload = $consumableService->buildConsumablesPayload($static);
                $calculatedCost = $payload['grand_total_weekly_cost'] ?? 0;

                if ($calculatedCost > 0) {
                    $costPerPlayer = $calculatedCost / 20;
                    // Round up to nearest 1000 gold (10 000 000 copper)
                    $roundedTax = (int) (ceil($costPerPlayer / 10000000) * 10000000);
                    $static->update(['weekly_tax_per_player' => $roundedTax]);
                }
            } catch (\Exception $e) {
                // Silently skip if something fails during calculation (e.g. missing prices)
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse needed for data update
    }
};
