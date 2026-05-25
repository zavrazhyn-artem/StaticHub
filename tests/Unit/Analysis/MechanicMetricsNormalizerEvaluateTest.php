<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use App\Services\Analysis\MechanicMetricsNormalizer;
use PHPUnit\Framework\TestCase;

class MechanicMetricsNormalizerEvaluateTest extends TestCase
{
    private function baseline(): array
    {
        return [
            'type' => 'tank_swap',
            'sample' => 12,
            'lethal_stacks' => 17,
            'metrics' => [
                'swap_at_stacks' => ['median' => 13.0, 'p5' => 11.0, 'p95' => 15.0, 'sample' => 24],
            ],
        ];
    }

    public function test_on_time_swap_at_high_stacks_is_not_flagged(): void
    {
        // The reported bug: swapping at 13 (normal) must NOT be flagged just for exceeding an old "7".
        $n = new MechanicMetricsNormalizer();
        $vector = ['by_player' => [
            'TankA' => ['swap_at_stacks' => [12, 13], 'max_held' => 14, 'death_stacks' => []],
            'TankB' => ['swap_at_stacks' => [13, 13], 'max_held' => 13, 'death_stacks' => []],
        ]];

        $eval = $n->evaluateTankSwap($vector, $this->baseline());
        $this->assertSame([], $eval['flagged']);
    }

    public function test_late_swap_beyond_p95_is_flagged(): void
    {
        $n = new MechanicMetricsNormalizer();
        $vector = ['by_player' => [
            'TankA' => ['swap_at_stacks' => [13, 19], 'max_held' => 19, 'death_stacks' => []],
        ]];

        $eval = $n->evaluateTankSwap($vector, $this->baseline());
        $this->assertArrayHasKey('TankA', $eval['flagged']);
        $this->assertStringContainsString('19', $eval['flagged']['TankA']);
    }

    public function test_death_at_lethal_stacks_is_flagged(): void
    {
        $n = new MechanicMetricsNormalizer();
        $vector = ['by_player' => [
            'TankB' => ['swap_at_stacks' => [13], 'max_held' => 17, 'death_stacks' => [17]],
        ]];

        $eval = $n->evaluateTankSwap($vector, $this->baseline());
        $this->assertArrayHasKey('TankB', $eval['flagged']);
    }

    public function test_missing_baseline_metrics_flags_nobody(): void
    {
        $n = new MechanicMetricsNormalizer();
        $vector = ['by_player' => ['TankA' => ['swap_at_stacks' => [99], 'max_held' => 99, 'death_stacks' => []]]];
        $eval = $n->evaluateTankSwap($vector, ['metrics' => []]);
        $this->assertSame([], $eval['flagged']);
    }
}
