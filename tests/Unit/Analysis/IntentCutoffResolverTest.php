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

    public function test_partition_moves_post_cutoff_deaths_and_records_metadata(): void
    {
        $deathsGrouped = [
            'Midnight Falls' => [
                'try_1' => [
                    ['player' => 'A', 'time_ms_relative' => 30000, 'fight_id' => 7],
                    ['player' => 'B', 'time_ms_relative' => 200000, 'fight_id' => 7],
                    ['player' => 'C', 'time_ms_relative' => 203000, 'fight_id' => 7],
                    ['player' => 'D', 'time_ms_relative' => 206000, 'fight_id' => 7],
                    ['player' => 'E', 'time_ms_relative' => 210000, 'fight_id' => 7],
                ],
            ],
        ];
        $phaseSummary = [
            'Midnight Falls' => [
                ['fight_id' => 7, 'outcome' => 'wipe', 'boss_pct' => 55.0, 'duration_s' => 212],
            ],
        ];

        $out = $this->resolver()->partition($deathsGrouped, $phaseSummary, []);

        // Kept: the early real death only.
        $this->assertCount(1, $out['deaths']['Midnight Falls']['try_1']);
        $this->assertSame('A', $out['deaths']['Midnight Falls']['try_1'][0]['player']);
        // Excluded: the 4-death terminal run.
        $this->assertCount(4, $out['excluded_deaths']['Midnight Falls']['try_1']);
        // Metadata keyed by fight_id.
        $this->assertSame(200000, $out['intent_cutoffs'][7]['cutoff_ms']);
        $this->assertSame('cascade_only', $out['intent_cutoffs'][7]['source']);
        $this->assertSame(4, $out['intent_cutoffs'][7]['excluded_count']);
    }

    public function test_partition_leaves_low_confidence_untouched(): void
    {
        $deathsGrouped = [
            'Boss' => [
                'try_1' => [
                    ['player' => 'RL', 'time_ms_relative' => 40000, 'fight_id' => 9],
                    ['player' => 'B', 'time_ms_relative' => 90000, 'fight_id' => 9],
                    ['player' => 'C', 'time_ms_relative' => 150000, 'fight_id' => 9],
                ],
            ],
        ];
        $phaseSummary = ['Boss' => [['fight_id' => 9, 'outcome' => 'wipe', 'boss_pct' => 70.0, 'duration_s' => 160]]];

        $out = $this->resolver()->partition($deathsGrouped, $phaseSummary, ['RL']);

        $this->assertCount(3, $out['deaths']['Boss']['try_1']);       // unchanged
        $this->assertArrayNotHasKey('Boss', $out['excluded_deaths']); // nothing excluded
        $this->assertArrayNotHasKey(9, $out['intent_cutoffs']);       // not recorded in 1a
    }

    public function test_partition_skips_kills(): void
    {
        $deathsGrouped = ['Boss' => ['try_1' => [
            ['player' => 'A', 'time_ms_relative' => 200000, 'fight_id' => 1],
            ['player' => 'B', 'time_ms_relative' => 203000, 'fight_id' => 1],
            ['player' => 'C', 'time_ms_relative' => 206000, 'fight_id' => 1],
        ]]];
        $phaseSummary = ['Boss' => [['fight_id' => 1, 'outcome' => 'kill', 'boss_pct' => 0.0, 'duration_s' => 210]]];

        $out = $this->resolver()->partition($deathsGrouped, $phaseSummary, []);
        $this->assertCount(3, $out['deaths']['Boss']['try_1']);
        $this->assertSame([], $out['excluded_deaths']);
    }
}
