<?php

namespace App\Services;

use App\Models\PriceSnapshot;
use App\Models\Recipe;
use App\Models\StaticGroup;
use Illuminate\Support\Collection;

class ConsumableService
{
    /**
     * Get raid recipes with calculated crafting costs and display metadata.
     */
    public function getRaidConsumablesData(StaticGroup $static = null): array
    {
        $raidRecipeNames = [
            'Voidlight Potion Cauldron',
            'Cauldron of Sin\'dorei Flasks',
            'Hearty Harandar Celebration',
        ];

        $recipes = Recipe::withNames($raidRecipeNames)
            ->with(['outputItem', 'ingredients.item'])
            ->get();

        $recipes = $recipes->map(function (Recipe $recipe) use ($static) {
            $craftingCost = 0;
            foreach ($recipe->ingredients as $ingredient) {
                $latestPrice = PriceSnapshot::latestPriceForItem($ingredient->item_id);
                $craftingCost += ($latestPrice ?? 0) * $ingredient->quantity;
            }

            $recipe->crafting_cost = $craftingCost / ($recipe->yield_quantity ?: 1);

            // Set display metadata and icons from WoW Zamimg
            $iconName = $recipe->outputItem?->icon;
            if ($iconName && !str_starts_with($iconName, 'http')) {
                $iconUrl = "https://wow.zamimg.com/images/wow/icons/large/{$iconName}.jpg";
            } else {
                $iconUrl = $iconName;
            }

            if (str_contains($recipe->name, 'Potion Cauldron')) {
                $recipe->display_icon_url = $iconUrl ?: 'https://wow.zamimg.com/images/wow/icons/large/inv_alchemy_elixir_empty.jpg';
                $recipe->display_icon = 'science';
                $recipe->display_color = 'mage';
                $recipe->default_quantity = 1;
            } elseif (str_contains($recipe->name, 'Sin\'dorei Flasks')) {
                $recipe->display_icon_url = $iconUrl ?: 'https://wow.zamimg.com/images/wow/icons/large/inv_10_alchemy_flask_color_red.jpg';
                $recipe->display_icon = 'science';
                $recipe->display_color = 'druid';
                $recipe->default_quantity = 1;
            } else {
                $recipe->display_icon_url = $iconUrl ?: 'https://wow.zamimg.com/images/wow/icons/large/inv_misc_food_buff_revelation.jpg';
                $recipe->display_icon = 'restaurant';
                $recipe->display_color = 'secondary';
                $recipe->default_quantity = 2;
            }

            // Override with static-specific quantities if they exist
            if ($static && isset($static->consumable_settings['quantities'][$recipe->name])) {
                $recipe->default_quantity = $static->consumable_settings['quantities'][$recipe->name];
            }

            // Compatibility for blade templates that might expect $recipe->icon from join
            $recipe->icon = $recipe->outputItem?->icon;

            return $recipe;
        });

        $individualPotionPrice = PriceSnapshot::latestPriceForItem(241308) ?? 0;

        $individualFlaskPrice = PriceSnapshot::latestPriceForItem(241320) ?? 0;

        $grandTotalWeeklyCost = $this->calculateGrandTotalWeeklyCost($recipes, $static);
        $activeMemberCount = $static ? $static->members()->count() : 0;
        $totalMemberSlots = 20;
        $guildTaxPerRaider = (int) ceil($grandTotalWeeklyCost / $totalMemberSlots);

        return [
            'recipes' => $recipes,
            'individualPotionPrice' => $individualPotionPrice,
            'individualFlaskPrice' => $individualFlaskPrice,
            'grand_total_weekly_cost' => $grandTotalWeeklyCost,
            'guild_tax_per_raider' => $guildTaxPerRaider,
            'active_member_count' => $activeMemberCount,
            'total_member_slots' => $totalMemberSlots,
        ];
    }

    /**
     * Update consumable settings for a static group.
     *
     * @param StaticGroup $static
     * @param array $quantities
     * @return void
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
     * Calculate the total weekly cost for all raid consumables.
     */
    protected function calculateGrandTotalWeeklyCost(Collection $recipes, StaticGroup $static = null): int
    {
        $raidDays = 3;
        if ($static && $static->raid_days) {
            $raidDays = count($static->raid_days);
        }

        return $recipes->sum(function ($recipe) use ($static, $raidDays) {
            $quantity = $recipe->default_quantity; // Use the already determined default_quantity (which includes static settings)

            return (int) ($recipe->crafting_cost * $quantity * $raidDays);
        });
    }
}
