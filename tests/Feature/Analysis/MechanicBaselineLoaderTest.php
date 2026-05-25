<?php

declare(strict_types=1);

namespace Tests\Feature\Analysis;

use App\Services\Analysis\MechanicBaselineLoader;
use Tests\TestCase;

class MechanicBaselineLoaderTest extends TestCase
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
            mechanics:
              - name: Vaelwing
                type: tank_swap
            empirical:
              difficulty: mythic
              mechanics:
                Vaelwing:
                  type: tank_swap
                  lethal_stacks: 17
                  metrics:
                    swap_at_stacks: { median: 13, p5: 11, p95: 15, sample: 24 }
            YAML);
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/test-boss.yml');
        parent::tearDown();
    }

    public function test_loads_empirical_block(): void
    {
        $loader = new MechanicBaselineLoader();
        $baseline = $loader->load('test-season', 'test-boss', 'mythic');

        $this->assertNotNull($baseline);
        $this->assertSame(17, $baseline['mechanics']['Vaelwing']['lethal_stacks']);
        $this->assertSame(15, $baseline['mechanics']['Vaelwing']['metrics']['swap_at_stacks']['p95']);
    }

    public function test_missing_file_returns_null(): void
    {
        $loader = new MechanicBaselineLoader();
        $this->assertNull($loader->load('test-season', 'no-such-boss', 'mythic'));
    }

    public function test_file_without_empirical_returns_null(): void
    {
        file_put_contents($this->dir . '/bare-boss.yml', "boss: 'Bare'\nmechanics: []\n");
        $loader = new MechanicBaselineLoader();
        $this->assertNull($loader->load('test-season', 'bare-boss', 'mythic'));
        @unlink($this->dir . '/bare-boss.yml');
    }
}
