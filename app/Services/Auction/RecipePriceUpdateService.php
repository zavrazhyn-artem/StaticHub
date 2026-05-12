<?php

declare(strict_types=1);

namespace App\Services\Auction;

use App\Models\Item;
use App\Models\Recipe;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Carbon;

class RecipePriceUpdateService
{
    /**
     * Persist freshly-synced item prices and recompute cached recipe costs.
     *
     * @param  array<int, int>  $itemIdToPrice  item_id => min unit_price (copper) from the latest auction sync
     */
    public function refreshAfterSync(array $itemIdToPrice, ?DateTimeInterface $now = null): void
    {
        if (empty($itemIdToPrice)) {
            return;
        }

        $timestamp = $now ?? Carbon::now();

        Item::query()->upsertLastPrices($itemIdToPrice, $timestamp);
        $this->refreshAllRecipeCosts($timestamp);
    }

    /**
     * Recompute crafting_cost for every recipe using items.last_price.
     * Recipes whose ingredients have no cached price yet are skipped.
     */
    public function refreshAllRecipeCosts(?DateTimeInterface $now = null): int
    {
        $timestamp = $now ?? Carbon::now();

        $recipes = Recipe::query()->withIngredients()->get();
        if ($recipes->isEmpty()) {
            return 0;
        }

        $ingredientItemIds = $recipes
            ->flatMap(fn (Recipe $recipe) => $recipe->ingredients->pluck('item_id'))
            ->unique()
            ->values()
            ->all();

        $priceMap = Item::query()->cachedPricesFor($ingredientItemIds);

        $recipeIdToCost = [];
        foreach ($recipes as $recipe) {
            $recipeIdToCost[$recipe->id] = $this->computeRecipeCost($recipe, $priceMap);
        }

        if (empty($recipeIdToCost)) {
            return 0;
        }

        return Recipe::query()->upsertCraftingCosts($recipeIdToCost, $timestamp);
    }

    /**
     * Sum ingredient prices × quantity, divide by yield, floor to int copper.
     * Missing prices are treated as 0 (matches legacy on-the-fly behavior).
     */
    private function computeRecipeCost(Recipe $recipe, \Illuminate\Support\Collection $priceMap): int
    {
        $total = 0;
        foreach ($recipe->ingredients as $ingredient) {
            $price = (int) ($priceMap->get($ingredient->item_id) ?? 0);
            $total += $price * (int) $ingredient->quantity;
        }

        $yield = $recipe->yield_quantity > 0 ? (int) $recipe->yield_quantity : 1;

        return intdiv($total, $yield);
    }
}
