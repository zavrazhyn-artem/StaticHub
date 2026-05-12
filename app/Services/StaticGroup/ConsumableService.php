<?php

declare(strict_types=1);

namespace App\Services\StaticGroup;

use App\Models\Item;
use App\Models\Recipe;
use App\Models\StaticGroup;
use App\Services\Cache\StaticCacheService;
use Illuminate\Support\Collection;

class ConsumableService
{
    private const REFERENCE_ITEM_POTION = 241308;
    private const REFERENCE_ITEM_FLASK = 241320;

    public function __construct(
        private readonly StaticCacheService $cache,
    ) {}

    /**
     * Orchestrator for building raid consumables data.
     */
    public function buildConsumablesPayload(?StaticGroup $static): array
    {
        $recipes = $this->fetchTargetRecipes();

        $referencePriceMap = Item::query()->cachedPricesFor([
            self::REFERENCE_ITEM_POTION,
            self::REFERENCE_ITEM_FLASK,
        ]);

        $recipes->each(function (Recipe $recipe) use ($static) {
            $this->applyDisplayMetadata($recipe);

            $recipe->quantity = $static
                ? $static->getConsumableQuantity($recipe->name, (int) $recipe->default_quantity)
                : (int) $recipe->default_quantity;
        });

        $referencePrices = [
            'individualPotionPrice' => (int) $referencePriceMap->get(self::REFERENCE_ITEM_POTION, 0),
            'individualFlaskPrice' => (int) $referencePriceMap->get(self::REFERENCE_ITEM_FLASK, 0),
        ];
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

        $this->cache->flushStatic((int) $static->id);
    }

    /**
     * Task: Fetch the target recipes with relations. crafting_cost is read from the column directly.
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
            ->with(['outputItem'])
            ->get();
    }

    /**
     * Apply display metadata and default quantities to a recipe.
     */
    private function applyDisplayMetadata(Recipe $recipe): void
    {
        $iconName = $recipe->outputItem?->icon;
        $iconUrl = ($iconName && !str_starts_with($iconName, 'http'))
            ? "https://wow.zamimg.com/images/wow/icons/large/{$iconName}.jpg"
            : $iconName;

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

        $recipe->icon = $recipe->outputItem?->icon;
    }

    /**
     * Task: Calculate grand totals and economic metrics from cached crafting_cost.
     */
    private function calculateEconomics(Collection $recipes, ?StaticGroup $static): array
    {
        $raidDays = ($static && $static->raid_days) ? count($static->raid_days) : 3;

        $grandTotalWeeklyCost = $recipes->sum(function (Recipe $recipe) use ($raidDays) {
            $cost = (int) ($recipe->crafting_cost ?? 0);
            $quantity = (int) ($recipe->quantity ?? $recipe->default_quantity);
            return $cost * $quantity * $raidDays;
        });

        $activeMemberCount = $static ? $static->members()->count() : 0;
        $totalMemberSlots = 20;
        $guildTaxPerRaider = $totalMemberSlots > 0
            ? (int) ceil($grandTotalWeeklyCost / $totalMemberSlots)
            : 0;

        return [
            'grand_total_weekly_cost' => $grandTotalWeeklyCost,
            'guild_tax_per_raider' => $guildTaxPerRaider,
            'active_member_count' => $activeMemberCount,
            'total_member_slots' => $totalMemberSlots,
        ];
    }
}
