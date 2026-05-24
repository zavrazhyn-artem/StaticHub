<?php

namespace Tests\Unit\Analysis;

use App\Services\Analysis\WipeDetector;
use PHPUnit\Framework\TestCase;

class WipeDetectorTerminalAbandonTest extends TestCase
{
    private function deaths(array $timesMs): array
    {
        return array_map(fn ($t) => ['player' => 'P', 'time_ms_relative' => $t], $timesMs);
    }

    public function test_trickle_run_to_end_returns_run_start(): void
    {
        $d = $this->deaths([200000, 203000, 206000, 210000, 214000]);
        // bossPct high (55%), fight ends at 216000 — last death within 8s of end.
        $this->assertSame(200000, (new WipeDetector())->detectTerminalAbandon($d, 55.0, 216000));
    }

    public function test_keeps_early_real_deaths_before_terminal_run(): void
    {
        $d = $this->deaths([30000, 35000, 200000, 204000, 208000]);
        $this->assertSame(200000, (new WipeDetector())->detectTerminalAbandon($d, 60.0, 210000));
    }

    public function test_progression_wipe_near_kill_returns_null(): void
    {
        $d = $this->deaths([200000, 203000, 206000, 210000, 214000]);
        $this->assertNull((new WipeDetector())->detectTerminalAbandon($d, 12.0, 216000));
    }

    public function test_sparse_deaths_return_null(): void
    {
        $d = $this->deaths([30000, 90000, 150000]);
        $this->assertNull((new WipeDetector())->detectTerminalAbandon($d, 70.0, 160000));
    }

    public function test_run_not_reaching_fight_end_returns_null(): void
    {
        // tight run but ends 30s before fight end → not an abandon
        $d = $this->deaths([100000, 103000, 106000]);
        $this->assertNull((new WipeDetector())->detectTerminalAbandon($d, 70.0, 160000));
    }

    public function test_fewer_than_min_run_returns_null(): void
    {
        $d = $this->deaths([210000, 214000]);
        $this->assertNull((new WipeDetector())->detectTerminalAbandon($d, 70.0, 216000));
    }
}
