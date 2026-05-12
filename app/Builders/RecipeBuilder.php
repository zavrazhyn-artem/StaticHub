<?php

namespace App\Builders;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RecipeBuilder extends Builder
{
    public function withNames(array $names): self
    {
        return $this->whereIn('name', $names);
    }

    /**
     * Eager-load ingredients (+ their items) for cost recomputation.
     */
    public function withIngredients(): self
    {
        return $this->with(['ingredients.item']);
    }

    /**
     * Bulk update recipes.crafting_cost + crafting_cost_at in chunked CASE statements.
     *
     * @param  array<int, int>  $recipeIdToCost  recipe_id => cached cost (copper)
     */
    public function upsertCraftingCosts(array $recipeIdToCost, DateTimeInterface $now): int
    {
        if (empty($recipeIdToCost)) {
            return 0;
        }

        $touched = 0;
        $timestamp = $now->format('Y-m-d H:i:s');

        foreach (array_chunk($recipeIdToCost, 500, true) as $chunk) {
            $ids = [];
            $caseParts = [];
            $bindings = [];

            foreach ($chunk as $recipeId => $cost) {
                $id = (int) $recipeId;
                $ids[] = $id;
                $caseParts[] = 'WHEN ? THEN ?';
                $bindings[] = $id;
                $bindings[] = (int) $cost;
            }

            $bindings[] = $timestamp;
            $bindings[] = $timestamp;

            $idList = implode(',', $ids);
            $caseSql = 'CASE id ' . implode(' ', $caseParts) . ' END';

            DB::update(
                "UPDATE recipes SET crafting_cost = {$caseSql}, crafting_cost_at = ?, updated_at = ? WHERE id IN ({$idList})",
                $bindings
            );

            $touched += count($chunk);
        }

        return $touched;
    }
}
