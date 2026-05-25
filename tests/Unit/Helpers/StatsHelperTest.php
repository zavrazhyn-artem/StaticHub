<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\StatsHelper;
use PHPUnit\Framework\TestCase;

class StatsHelperTest extends TestCase
{
    public function test_percentile_interpolates(): void
    {
        $values = [10, 20, 30, 40];
        $this->assertSame(10.0, StatsHelper::percentile($values, 0));
        $this->assertSame(40.0, StatsHelper::percentile($values, 100));
        $this->assertSame(25.0, StatsHelper::percentile($values, 50));
    }

    public function test_percentile_empty_returns_zero(): void
    {
        $this->assertSame(0.0, StatsHelper::percentile([], 50));
    }

    public function test_summarize_returns_envelope(): void
    {
        $out = StatsHelper::summarize([11, 12, 13, 13, 14, 15]);
        $this->assertSame(6, $out['sample']);
        $this->assertEqualsWithDelta(13.0, $out['median'], 0.01);
        $this->assertGreaterThanOrEqual($out['p5'], $out['median']);
        $this->assertGreaterThanOrEqual($out['median'], $out['p95']);
    }

    public function test_summarize_empty_returns_null(): void
    {
        $this->assertNull(StatsHelper::summarize([]));
    }
}
