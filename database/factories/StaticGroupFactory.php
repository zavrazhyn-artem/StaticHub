<?php

namespace Database\Factories;

use App\Models\StaticGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StaticGroupFactory extends Factory
{
    protected $model = StaticGroup::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'slug' => fake()->unique()->slug(),
            'region' => 'eu',
            'owner_id' => User::factory(),
        ];
    }
}
