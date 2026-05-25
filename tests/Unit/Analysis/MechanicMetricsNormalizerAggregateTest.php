<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use App\Services\Analysis\MechanicMetricsNormalizer;
use PHPUnit\Framework\TestCase;

class MechanicMetricsNormalizerAggregateTest extends TestCase
{
    public function test_aggregate_builds_empirical_entry(): void
    {
        $n = new MechanicMetricsNormalizer();
        $vectors = [
            ['observations' => ['swap_at_stacks' => [12, 13], 'swap_gap_ms' => [800], 'max_stacks_held' => [14]], 'death_stacks' => [18]],
            ['observations' => ['swap_at_stacks' => [13, 14], 'swap_gap_ms' => [900], 'max_stacks_held' => [15]], 'death_stacks' => [17]],
        ];

        $entry = $n->aggregateTankSwap($vectors);

        $this->assertSame('tank_swap', $entry['type']);
        $this->assertSame(2, $entry['sample']);
        $this->assertSame(17, $entry['lethal_stacks']); // min death stack observed
        $this->assertArrayHasKey('swap_at_stacks', $entry['metrics']);
        $this->assertSame(4, $entry['metrics']['swap_at_stacks']['sample']); // 12,13,13,14
        $this->assertEqualsWithDelta(13.0, $entry['metrics']['swap_at_stacks']['median'], 0.5);
    }

    public function test_aggregate_no_deaths_null_lethal(): void
    {
        $n = new MechanicMetricsNormalizer();
        $vectors = [
            ['observations' => ['swap_at_stacks' => [12], 'swap_gap_ms' => [800], 'max_stacks_held' => [13]], 'death_stacks' => []],
        ];
        $entry = $n->aggregateTankSwap($vectors);
        $this->assertNull($entry['lethal_stacks']);
    }
}
