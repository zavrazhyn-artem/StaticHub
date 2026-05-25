<?php

declare(strict_types=1);

namespace Tests\Feature\Analysis;

use App\Services\Analysis\WclService;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class RefreshMechanicBaselinesCommandTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = resource_path('boss-timelines/test-season/mythic');
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0775, true);
        }
        file_put_contents($this->dir . '/test-boss.yml', <<<YAML
            boss: 'Test Boss'
            wcl_encounter_id: 9999
            mechanics:
              - name: Vaelwing
                type: tank_swap
            YAML);
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/test-boss.yml');
        parent::tearDown();
    }

    public function test_harvest_writes_empirical_tank_swap_block(): void
    {
        $logData = ['debuff_stacks' => ['Vaelwing' => [
            'max_stacks_per_player' => ['TankA' => 14, 'TankB' => 13],
            'stacks_at_death_per_player' => [],
            'swap_timing' => ['swaps' => [
                ['outgoing_player' => 'TankA', 'stacks_at_swap' => 12, 'gap_ms' => 800],
                ['outgoing_player' => 'TankB', 'stacks_at_swap' => 13, 'gap_ms' => 900],
            ]],
        ]]];

        $mock = $this->createMock(WclService::class);
        $mock->method('fetchCharacterRankings')->willReturn([
            ['name' => 'X', 'report' => ['code' => 'AAA', 'fightID' => 1]],
            ['name' => 'Y', 'report' => ['code' => 'AAA', 'fightID' => 1]], // dup report → deduped
            ['name' => 'Z', 'report' => ['code' => 'BBB', 'fightID' => 2]],
        ]);
        $mock->method('getLogSummary')->willReturn($logData);
        $this->app->instance(WclService::class, $mock);

        $this->artisan('refresh:mechanic-baselines', [
            '--bosses' => 'test-boss',
            '--season' => 'test-season',
            '--difficulty' => 5,
            '--kills' => 5,
            '--pace' => 0,
            '--force' => true,
        ])->assertExitCode(0);

        $yaml = Yaml::parseFile($this->dir . '/test-boss.yml');
        $entry = $yaml['empirical']['mechanics']['Vaelwing'];
        $this->assertSame('tank_swap', $entry['type']);
        $this->assertSame(2, $entry['sample']);            // 2 unique kill logs (AAA, BBB)
        $this->assertArrayHasKey('swap_at_stacks', $entry['metrics']);
        // authored mechanics preserved
        $this->assertSame('Vaelwing', $yaml['mechanics'][0]['name']);
    }
}
