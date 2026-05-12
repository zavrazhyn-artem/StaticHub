<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\PriceSnapshot;
use App\Models\Recipe;
use App\Services\Auction\RecipePriceUpdateService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('auctions:backfill-cached-prices')]
#[Description('One-shot: populate items.last_price and recipes.crafting_cost from current price_snapshots.')]
class BackfillCachedPricesCommand extends Command
{
    public function handle(RecipePriceUpdateService $recipePriceUpdateService): int
    {
        $now = Carbon::now();

        $itemIds = Item::query()->pluck('id')->all();
        if (empty($itemIds)) {
            $this->warn('No items to backfill.');
            return self::SUCCESS;
        }

        $this->info(sprintf('Reading latest snapshot price for %d items…', count($itemIds)));
        $priceMap = PriceSnapshot::query()->latestPricesForItems($itemIds);

        if ($priceMap->isEmpty()) {
            $this->warn('No snapshot prices found — nothing to backfill.');
            return self::SUCCESS;
        }

        $itemIdToPrice = $priceMap->map(fn ($price) => (int) $price)->all();

        $itemsTouched = Item::query()->upsertLastPrices($itemIdToPrice, $now);
        $this->info("Updated items.last_price for {$itemsTouched} items.");

        $recipesTouched = $recipePriceUpdateService->refreshAllRecipeCosts($now);
        $this->info("Updated recipes.crafting_cost for {$recipesTouched} recipes.");

        $this->printRecipeCoverage();

        return self::SUCCESS;
    }

    private function printRecipeCoverage(): void
    {
        $recipes = Recipe::query()->with(['ingredients.item'])->get();
        if ($recipes->isEmpty()) {
            return;
        }

        $this->line('');
        $this->line('Recipe coverage:');
        foreach ($recipes as $recipe) {
            $missing = $recipe->ingredients->filter(fn ($i) => $i->item?->last_price === null);
            $cost = $recipe->crafting_cost !== null ? intdiv((int) $recipe->crafting_cost, 10000) . 'G' : 'NULL';
            $line = sprintf('  • %-40s cost=%-10s ingredients=%d', $recipe->name, $cost, $recipe->ingredients->count());
            if ($missing->isNotEmpty()) {
                $line .= ' missing_prices=' . $missing->pluck('item_id')->implode(',');
                $this->warn($line);
            } else {
                $this->line($line);
            }
        }
    }
}
