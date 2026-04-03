<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\Analysis\WclService;

$wcl = new WclService();
$reportId = 'aCf93mdkhgNRKFZq';

// Mock roster based on what we saw in previous test outputs
$rosterNames = ['Jaina', 'Arthas', 'Sylvanas', 'Thrall', 'Baine'];

try {
    echo "Fetching optimized report data for: $reportId\n";
    $data = $wcl->getLogSummary($reportId, $rosterNames);

    if (isset($data['error'])) {
        echo "Error: " . $data['error'] . "\n";
        if (isset($data['fights'])) {
            echo "Fights found: " . count($data['fights']) . "\n";
        }
        exit;
    }

    echo "Raid Title: " . $data['raid_title'] . "\n";
    echo "Players in payload: " . count($data['players']) . "\n";
    foreach ($data['players'] as $player) {
        echo " - " . $player['name'] . " (" . $player['subType'] . ")\n";
    }

    echo "\nCooldown Usage (Flat List):\n";
    foreach ($data['cooldown_usage'] as $usage) {
        echo " - {$usage['player']} used {$usage['ability']} in fight {$usage['fight_id']}\n";
    }
    if (empty($data['cooldown_usage'])) {
        echo " - No cooldown usage found.\n";
    }

    echo "\nMajor Damage Taken (Filtered):\n";
    foreach ($data['major_damage_taken'] as $damage) {
        echo " - Ability: {$damage['ability']}, Total Damage: {$damage['total_damage_to_raid']}\n";
        echo "   Top Victims: " . json_encode($damage['biggest_victims']) . "\n";
    }

} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
