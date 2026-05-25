<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use App\Helpers\WclReportParserHelper;
use PHPUnit\Framework\TestCase;

class SwapTimingDetailTest extends TestCase
{
    public function test_same_tank_reapply_does_not_fabricate_a_swap(): void
    {
        $actorMap = [1 => 'TankA', 2 => 'TankB'];
        // TankA removes at t=1000, RE-TAKES it himself at t=1500 (no swap),
        // then TankB applies at t=4000. The stale removal must NOT produce a
        // bogus swap with a ~3000ms gap measured from t=1000.
        $events = [
            ['type' => 'applydebuff',  'targetID' => 1, 'fight' => 7, 'timestamp' => 0],
            ['type' => 'removedebuff', 'targetID' => 1, 'fight' => 7, 'timestamp' => 1000],
            ['type' => 'applydebuff',  'targetID' => 1, 'fight' => 7, 'timestamp' => 1500],
            ['type' => 'applydebuff',  'targetID' => 2, 'fight' => 7, 'timestamp' => 4000],
        ];

        $parsed = WclReportParserHelper::parseDebuffStacks($events, $actorMap);
        $swaps = $parsed['swap_timing']['swaps'] ?? [];

        $this->assertSame([], $swaps, 'same-tank re-apply then a later different-tank apply must not fabricate a swap');
    }

    public function test_swap_timing_reports_outgoing_player_and_stacks(): void
    {
        $actorMap = [1 => 'TankA', 2 => 'TankB'];
        // TankA stacks to 3 then swaps out at t=3000; TankB taunts at t=3500.
        $events = [
            ['type' => 'applydebuff',      'targetID' => 1, 'fight' => 7, 'timestamp' => 0],
            ['type' => 'applydebuffstack', 'targetID' => 1, 'fight' => 7, 'timestamp' => 1000, 'stack' => 2],
            ['type' => 'applydebuffstack', 'targetID' => 1, 'fight' => 7, 'timestamp' => 2000, 'stack' => 3],
            ['type' => 'removedebuff',     'targetID' => 1, 'fight' => 7, 'timestamp' => 3000],
            ['type' => 'applydebuff',      'targetID' => 2, 'fight' => 7, 'timestamp' => 3500],
        ];

        $parsed = WclReportParserHelper::parseDebuffStacks($events, $actorMap);
        $swaps = $parsed['swap_timing']['swaps'] ?? null;

        $this->assertIsArray($swaps);
        $this->assertCount(1, $swaps);
        $this->assertSame('TankA', $swaps[0]['outgoing_player']);
        $this->assertSame(3, $swaps[0]['stacks_at_swap']);
        $this->assertSame(500, $swaps[0]['gap_ms']);
    }
}
