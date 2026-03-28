<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConsumablesController extends Controller
{
    public function index()
    {
        $raidRecipeNames = [
            'Voidlight Potion Cauldron',
            'Cauldron of Sin\'dorei Flasks',
            'Hearty Harandar Celebration',
        ];

        $recipes = DB::table('recipes')
            ->leftJoin('items', 'recipes.output_item_id', '=', 'items.id')
            ->whereIn('recipes.name', $raidRecipeNames)
            ->select('recipes.*', 'items.icon')
            ->get();

        $recipes = $recipes->map(function ($recipe) {
            $ingredients = DB::table('recipe_ingredients')
                ->join('items', 'recipe_ingredients.item_id', '=', 'items.id')
                ->where('recipe_id', $recipe->id)
                ->select('items.id', 'items.name', 'items.icon', 'recipe_ingredients.quantity')
                ->get();

            $craftingCost = 0;
            foreach ($ingredients as $ingredient) {
                $latestPrice = DB::table('price_snapshots')
                    ->where('item_id', $ingredient->id)
                    ->orderBy('created_at', 'desc')
                    ->value('price');
                $craftingCost += ($latestPrice ?? 0) * $ingredient->quantity;
            }

            $recipe->crafting_cost = $craftingCost / ($recipe->yield_quantity ?: 1);

            // Set default quantities for calculator
            if (str_contains($recipe->name, 'Cauldron')) {
                $recipe->default_quantity = 1;
            } else {
                $recipe->default_quantity = 2;
            }

            return $recipe;
        });

        // Individual Potion (item_id: 241308) and Individual Flask (item_id: 241320)
        $individualPotionPrice = DB::table('price_snapshots')
            ->where('item_id', 241308)
            ->orderBy('created_at', 'desc')
            ->value('price') ?? 0;

        $individualFlaskPrice = DB::table('price_snapshots')
            ->where('item_id', 241320)
            ->orderBy('created_at', 'desc')
            ->value('price') ?? 0;

        return view('consumables.index', compact('recipes', 'individualPotionPrice', 'individualFlaskPrice'));
    }

}
