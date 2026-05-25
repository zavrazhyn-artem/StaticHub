<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use App\Services\Analysis\MechanicMetricsNormalizer;
use PHPUnit\Framework\TestCase;

class MechanicMetricsNormalizerTankSwapTest extends TestCase
{
    private function debuffEntry(): array
    {
        return [
            'max_stacks_per_player' => ['TankA' => 14, 'TankB' => 13],
            'stacks_at_death_per_player' => [
                'TankB' => [['fight_id' => 7, 'max_stacks' => 17, 'killing_blow' => 'Vaelwing']],
            ],
            'swap_timing' => [
                'swaps' => [
                    ['outgoing_player' => 'TankA', 'stacks_at_swap' => 12, 'gap_ms' => 800],
                    ['outgoing_player' => 'TankB', 'stacks_at_swap' => 13, 'gap_ms' => 900],
                    ['outgoing_player' => 'TankA', 'stacks_at_swap' => 13, 'gap_ms' => 750],
                ],
            ],
        ];
    }

    public function test_tank_swap_builds_observation_vectors(): void
    {
        $n = new MechanicMetricsNormalizer();
        $v = $n->tankSwap($this->debuffEntry());

        $this->assertSame([12, 13, 13], $v['observations']['swap_at_stacks']);
        $this->assertSame([800, 900, 750], $v['observations']['swap_gap_ms']);
        $this->assertSame([14, 13], array_values($v['observations']['max_stacks_held']));
        $this->assertSame([17], $v['death_stacks']);
    }

    public function test_tank_swap_by_player_attribution(): void
    {
        $n = new MechanicMetricsNormalizer();
        $v = $n->tankSwap($this->debuffEntry());

        $this->assertSame([12, 13], $v['by_player']['TankA']['swap_at_stacks']);
        $this->assertSame(14, $v['by_player']['TankA']['max_held']);
        $this->assertSame([], $v['by_player']['TankA']['death_stacks']);
        $this->assertSame([17], $v['by_player']['TankB']['death_stacks']);
    }

    public function test_debuff_matcher_token_based(): void
    {
        $this->assertTrue(MechanicMetricsNormalizer::debuffMatchesMechanic('Vaelwing', 'Vaelwing', []));
        $this->assertTrue(MechanicMetricsNormalizer::debuffMatchesMechanic('Vaelwing Stacks', 'Vaelwing', []));
        $this->assertTrue(MechanicMetricsNormalizer::debuffMatchesMechanic('Rakfang Mark', 'Something', ['Rakfang']));
        $this->assertFalse(MechanicMetricsNormalizer::debuffMatchesMechanic('Nullzone', 'Vaelwing', []));
    }
}
