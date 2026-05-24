<?php

namespace Tests\Feature\Analysis;

use App\Services\Analysis\FightBreakdownBuilder;
use Tests\TestCase;

class FightBreakdownExcludedDeathsTest extends TestCase
{
    public function test_fight_carries_excluded_deaths_and_intent_cutoff(): void
    {
        $builder = app(FightBreakdownBuilder::class);

        $tries = [
            ['fight_id' => 7, 'outcome' => 'wipe', 'duration_s' => 212, 'boss_pct' => 55.0],
        ];
        $deathsByTry = [
            'try_1' => [['player' => 'A', 'time_ms_relative' => 30000, 'fight_id' => 7]],
        ];
        $excludedByTry = [
            'try_1' => [
                ['player' => 'B', 'time_ms_relative' => 200000, 'fight_id' => 7],
                ['player' => 'C', 'time_ms_relative' => 203000, 'fight_id' => 7],
            ],
        ];
        $intentCutoffs = [
            7 => ['cutoff_ms' => 200000, 'source' => 'cascade_only', 'confidence' => 'medium', 'excluded_count' => 2],
        ];

        $fights = $builder->build($tries, $deathsByTry, [], [], $excludedByTry, $intentCutoffs);

        $this->assertCount(2, $fights[0]['excluded_deaths']);
        $this->assertSame(200000, $fights[0]['intent_cutoff']['cutoff_ms']);
        $this->assertSame('cascade_only', $fights[0]['intent_cutoff']['source']);
    }

    public function test_fight_without_cutoff_has_empty_bucket_and_null_cutoff(): void
    {
        $builder = app(FightBreakdownBuilder::class);
        $tries = [['fight_id' => 1, 'outcome' => 'kill', 'duration_s' => 210, 'boss_pct' => 0.0]];
        $deathsByTry = ['try_1' => []];

        $fights = $builder->build($tries, $deathsByTry, [], []);

        $this->assertSame([], $fights[0]['excluded_deaths']);
        $this->assertNull($fights[0]['intent_cutoff']);
    }
}
