<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\ConsumableMetadataHelper;
use App\Models\PriceSnapshot;
use App\Models\Recipe;
use App\Models\StaticGroup;
use Illuminate\Support\Collection;

class ConsumableService
{
    /**
     * Orchestrator for building raid consumables data.
     */
    public function buildConsumablesPayload(?StaticGroup $static): array
    {
        $recipes = $this->fetchTargetRecipes();

        $recipes->each(function (Recipe $recipe) use ($static) {
            $recipe->crafting_cost = $this->calculateRecipeCost($recipe);
            ConsumableMetadataHelper::applyDisplayMetadata($recipe);

            // Fetch the quantity, either from static settings or default from the helper
            $recipe->quantity = $static
                ? $static->getConsumableQuantity($recipe->name, (int) $recipe->default_quantity)
                : (int) $recipe->default_quantity;
        });

        $referencePrices = $this->fetchReferencePrices();
        $economics = $this->calculateEconomics($recipes, $static);

        return array_merge([
            'recipes' => $recipes,
        ], $referencePrices, $economics);
    }

    /**
     * Update consumable settings for a static group.
     */
    public function updateSettings(StaticGroup $static, array $quantities): void
    {
        $static->update([
            'consumable_settings' => [
                'quantities' => $quantities,
            ]
        ]);
    }

    /**
     * Task: Fetch the target recipes with relations.
     */
    private function fetchTargetRecipes(): Collection
    {
        $raidRecipeNames = [
            'Voidlight Potion Cauldron',
            'Cauldron of Sin\'dorei Flasks',
            'Hearty Harandar Celebration',
        ];

        return Recipe::query()
            ->withNames($raidRecipeNames)
            ->with(['outputItem', 'ingredients.item'])
            ->get();
    }

    /**
     * Task: Calculate crafting cost for a recipe.
     */
    private function calculateRecipeCost(Recipe $recipe): float
    {
        $totalCost = 0;
        foreach ($recipe->ingredients as $ingredient) {
            $latestPrice = PriceSnapshot::latestPriceForItem($ingredient->item_id);
            $totalCost += ($latestPrice ?? 0) * $ingredient->quantity;
        }

        return (float) ($totalCost / ($recipe->yield_quantity ?: 1));
    }

    /**
     * Task: Fetch individual potion and flask prices.
     */
    private function fetchReferencePrices(): array
    {
        return [
            'individualPotionPrice' => PriceSnapshot::latestPriceForItem(241308) ?? 0,
            'individualFlaskPrice' => PriceSnapshot::latestPriceForItem(241320) ?? 0,
        ];
    }

    /**
     * Task: Calculate grand totals and economic metrics.
     */
    private function calculateEconomics(Collection $recipes, ?StaticGroup $static): array
    {
        $raidDays = ($static && $static->raid_days) ? count($static->raid_days) : 3;

        $grandTotalWeeklyCost = $recipes->sum(function (Recipe $recipe) use ($raidDays) {
            return (int) ($recipe->crafting_cost * ($recipe->quantity ?? $recipe->default_quantity) * $raidDays);
        });

        $activeMemberCount = $static ? $static->members()->count() : 0;
        $totalMemberSlots = 20;
        $guildTaxPerRaider = (int) ceil($grandTotalWeeklyCost / $totalMemberSlots);

        return [
            'grand_total_weekly_cost' => $grandTotalWeeklyCost,
            'guild_tax_per_raider' => $guildTaxPerRaider,
            'active_member_count' => $activeMemberCount,
            'total_member_slots' => $totalMemberSlots,
        ];
    }
}
