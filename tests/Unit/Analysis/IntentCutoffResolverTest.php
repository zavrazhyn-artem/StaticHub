<?php

namespace Tests\Unit\Analysis;

use App\Services\Analysis\IntentCutoffResolver;
use App\Services\Analysis\WipeDetector;
use PHPUnit\Framework\TestCase;

class IntentCutoffResolverTest extends TestCase
{
    private function resolver(): IntentCutoffResolver
    {
        return new IntentCutoffResolver(new WipeDetector());
    }

    private function deaths(array $rows): array
    {
        // $rows = [[player, timeMs], ...]
        return array_map(fn ($r) => ['player' => $r[0], 'time_ms_relative' => $r[1], 'fight_id' => 7], $rows);
    }

    public function test_kill_is_never_cut(): void
    {
        $d = $this->deaths([['P', 200000], ['P', 203000], ['P', 206000]]);
        $meta = ['outcome' => 'kill', 'boss_pct' => 0.0, 'duration_s' => 210, 'fight_id' => 7];
        $this->assertNull($this->resolver()->resolve($d, $meta, []));
    }

    public function test_cascade_only_is_medium(): void
    {
        $d = $this->deaths([['A', 200000], ['B', 203000], ['C', 206000], ['D', 210000], ['E', 214000]]);
        $meta = ['outcome' => 'wipe', 'boss_pct' => 55.0, 'duration_s' => 216, 'fight_id' => 7];
        $res = $this->resolver()->resolve($d, $meta, []); // no anchor
        $this->assertSame(['cutoff_ms' => 200000, 'source' => 'cascade_only', 'confidence' => 'medium'], $res);
    }

    public function test_anchor_within_cascade_is_high(): void
    {
        $d = $this->deaths([['A', 200000], ['RL', 201000], ['C', 206000], ['D', 210000], ['E', 214000]]);
        $meta = ['outcome' => 'wipe', 'boss_pct' => 55.0, 'duration_s' => 216, 'fight_id' => 7];
        $res = $this->resolver()->resolve($d, $meta, ['RL']);
        $this->assertSame(['cutoff_ms' => 200000, 'source' => 'anchor+cascade', 'confidence' => 'high'], $res);
    }

    public function test_anchor_only_no_cascade_is_low(): void
    {
        // Anchor dies early, only sparse deaths after → no terminal run.
        $d = $this->deaths([['RL', 40000], ['B', 90000], ['C', 150000]]);
        $meta = ['outcome' => 'wipe', 'boss_pct' => 70.0, 'duration_s' => 160, 'fight_id' => 7];
        $res = $this->resolver()->resolve($d, $meta, ['RL']);
        $this->assertSame(['cutoff_ms' => 40000, 'source' => 'anchor_only', 'confidence' => 'low'], $res);
    }

    public function test_no_signal_returns_null(): void
    {
        $d = $this->deaths([['B', 90000], ['C', 150000]]);
        $meta = ['outcome' => 'wipe', 'boss_pct' => 70.0, 'duration_s' => 160, 'fight_id' => 7];
        $this->assertNull($this->resolver()->resolve($d, $meta, []));
    }
}
