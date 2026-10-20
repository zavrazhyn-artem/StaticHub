<?php

declare(strict_types=1);

namespace Tests\Feature\Analysis;

use App\Services\Analysis\WclService;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class RefreshMechanicBaselinesCommandTest extends TestCase
{
    private string $tacticsDir;
    private string $tacticsFile;
    private string $bodyMarker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tacticsDir = resource_path('tactics');
        $this->tacticsFile = $this->tacticsDir . '/test-mechanic-baseline-boss.md';
        $this->bodyMarker = "# Test Boss\n\nThe markdown body that must survive untouched.\n";

        $frontmatter = <<<YAML
            boss: 'Test Mechanic Baseline Boss'
            wcl_encounter_id: 99991
            difficulty_variants: [mythic]
            mechanics:
              - name: Vaelwing
                type: tank_swap
                ability_ids: [123456]
                description: 'A debuff that stacks; tanks swap before lethal.'
            YAML;

        file_put_contents($this->tacticsFile, "---\n{$frontmatter}\n---\n{$this->bodyMarker}");
    }

    protected function tearDown(): void
    {
        @unlink($this->tacticsFile);
        $cacheDir = storage_path('app/mechanic-baseline-cache/test-mechanic-baseline-boss');
        if (is_dir($cacheDir)) {
            array_map('unlink', glob("{$cacheDir}/*") ?: []);
            @rmdir($cacheDir);
        }
        parent::tearDown();
    }

    public function test_harvest_writes_empirical_block_into_tactics_frontmatter(): void
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
        $mock->method('executeGraphql')->willReturn([
            'worldData' => ['encounter' => ['characterRankings' => [
                'rankings' => [
                    ['name' => 'X', 'report' => ['code' => 'AAA', 'fightID' => 1]],
                    ['name' => 'Y', 'report' => ['code' => 'AAA', 'fightID' => 1]], // dup report → deduped
                    ['name' => 'Z', 'report' => ['code' => 'BBB', 'fightID' => 2]],
                ],
            ]]],
        ]);
        $mock->method('getLogSummary')->willReturn($logData);
        $this->app->instance(WclService::class, $mock);

        $this->artisan('refresh:mechanic-baselines', [
            '--bosses' => 'test-mechanic-baseline-boss',
            '--difficulty' => 5,
            '--kills' => 5,
            '--pace' => 0,
            '--force' => true,
        ])->assertExitCode(0);

        $written = file_get_contents($this->tacticsFile);
        $this->assertNotFalse($written);
        $this->assertMatchesRegularExpression('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $written);

        preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $written, $m);
        $frontmatter = Yaml::parse($m[1]);
        $body = $m[2];

        $this->assertSame($this->bodyMarker, $body, 'Markdown body must survive untouched.');
        $this->assertSame('Test Mechanic Baseline Boss', $frontmatter['boss']);
        $this->assertSame(99991, $frontmatter['wcl_encounter_id']);
        $this->assertSame('Vaelwing', $frontmatter['mechanics'][0]['name']);

        $entry = $frontmatter['empirical']['mechanics']['Vaelwing'];
        $this->assertSame('tank_swap', $entry['type']);
        $this->assertSame(2, $entry['sample']); // 2 unique kill logs (AAA, BBB)
        $this->assertArrayHasKey('swap_at_stacks', $entry['metrics']);
    }
}
