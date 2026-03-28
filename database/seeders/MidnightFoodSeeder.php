<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MidnightFoodSeeder extends Seeder
{
    public function run()
    {
        $recipesToSeed = [
            [
                'name' => 'Harandar Celebration',
                'output_item_id' => 255846,
                'profession' => 'Cooking',
                'yield_quantity' => 2,
                'ingredients' => [
                    ['id' => 242640, 'name' => 'Plant Protein', 'quantity' => 10],
                    ['id' => 242647, 'name' => 'Tavern Fixings', 'quantity' => 4],
                    ['id' => 242641, 'name' => 'Cooking Spirits', 'quantity' => 3],
                    ['id' => 236951, 'name' => 'Mote of Wild Magic', 'quantity' => 3],
                    ['id' => 251285, 'name' => 'Petrified Root', 'quantity' => 1],
                    ['id' => 236774, 'name' => 'Azeroot', 'quantity' => 3],
                ]
            ],
            [
                'name' => 'Hearty Harandar Celebration',
                'output_item_id' => 266996,
                'profession' => 'Cooking',
                'yield_quantity' => 1,
                'ingredients' => [
                    ['id' => 255846, 'name' => 'Harandar Celebration', 'quantity' => 10],
                ]
            ]
        ];

        $this->seedRecipes($recipesToSeed);
        $this->command->info('Midnight Food successfully seeded!');
    }

    private function seedRecipes(array $recipesToSeed)
    {
        $allItems = [];
        foreach ($recipesToSeed as $recipeData) {
            $allItems[$recipeData['output_item_id']] = [
                'id' => $recipeData['output_item_id'],
                'name' => $recipeData['name'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
            foreach ($recipeData['ingredients'] as $ing) {
                $allItems[$ing['id']] = [
                    'id' => $ing['id'],
                    'name' => $ing['name'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('items')->insertOrIgnore(array_values($allItems));

        foreach ($recipesToSeed as $recipeData) {
            DB::table('recipes')->updateOrInsert(
                ['output_item_id' => $recipeData['output_item_id'], 'profession' => $recipeData['profession']],
                [
                    'name' => $recipeData['name'],
                    'yield_quantity' => $recipeData['yield_quantity'],
                    'updated_at' => now(),
                ]
            );

            $recipe = DB::table('recipes')->where('output_item_id', $recipeData['output_item_id'])->first();

            DB::table('recipe_ingredients')->where('recipe_id', $recipe->id)->delete();

            $ingredientsToInsert = [];
            foreach ($recipeData['ingredients'] as $ing) {
                $ingredientsToInsert[] = [
                    'recipe_id' => $recipe->id,
                    'item_id' => $ing['id'],
                    'quantity' => $ing['quantity'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('recipe_ingredients')->insert($ingredientsToInsert);
        }
    }
}


