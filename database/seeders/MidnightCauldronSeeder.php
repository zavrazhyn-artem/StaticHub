<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MidnightCauldronSeeder extends Seeder
{
    public function run()
    {
        $recipesToSeed = [
            [
                'name' => 'Voidlight Potion Cauldron',
                'output_item_id' => 241284,
                'profession' => 'Alchemy',
                'ingredients' => [
                    ['id' => 236780, 'name' => 'Nocturnal Lotus', 'quantity' => 1],
                    ['id' => 242651, 'name' => 'Stabilized Derivate', 'quantity' => 5],
                    ['id' => 251285, 'name' => 'Petrified Root', 'quantity' => 4],
                    ['id' => 240990, 'name' => 'Sunglass Vial', 'quantity' => 20],
                    ['id' => 241282, 'name' => 'Wondrous Synergist', 'quantity' => 4],
                ]
            ],
            [
                'name' => 'Cauldron of Sin\'dorei Flasks',
                'output_item_id' => 241318,
                'profession' => 'Alchemy',
                'ingredients' => [
                    ['id' => 236780, 'name' => 'Nocturnal Lotus', 'quantity' => 1],
                    ['id' => 242651, 'name' => 'Stabilized Derivate', 'quantity' => 5],
                    ['id' => 251285, 'name' => 'Petrified Root', 'quantity' => 4],
                    ['id' => 240990, 'name' => 'Sunglass Vial', 'quantity' => 20],
                    ['id' => 241282, 'name' => 'Wondrous Synergist', 'quantity' => 4],
                ]
            ],
        ];

        $allItems = [];

        foreach ($recipesToSeed as $recipeData) {
            // Додаємо сам результат крафту до списку предметів
            $allItems[$recipeData['output_item_id']] = [
                'id' => $recipeData['output_item_id'],
                'name' => $recipeData['name'],
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Додаємо інгредієнти до списку предметів
            foreach ($recipeData['ingredients'] as $ing) {
                $allItems[$ing['id']] = [
                    'id' => $ing['id'],
                    'name' => $ing['name'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // 1. Записуємо всі унікальні предмети в базу
        DB::table('items')->insertOrIgnore(array_values($allItems));

        // 2. Записуємо рецепти та їхні зв'язки
        foreach ($recipesToSeed as $recipeData) {
            $recipeId = DB::table('recipes')->insertGetId([
                'name' => $recipeData['name'],
                'profession' => $recipeData['profession'],
                'output_item_id' => $recipeData['output_item_id'],
                'yield_quantity' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $ingredientsToInsert = [];
            foreach ($recipeData['ingredients'] as $ing) {
                $ingredientsToInsert[] = [
                    'recipe_id' => $recipeId,
                    'item_id' => $ing['id'],
                    'quantity' => $ing['quantity'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('recipe_ingredients')->insert($ingredientsToInsert);
        }

        $this->command->info('All cauldrons and recipes successfully seeded!');
    }
}
