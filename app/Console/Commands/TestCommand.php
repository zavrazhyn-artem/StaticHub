<?php

namespace App\Console\Commands;

use App\Enums\Locale;
use App\Enums\StaticGroup\Role;
use App\Models\StaticGroup;
use App\Services\Analysis\WclService;
use Illuminate\Console\Command;

class TestCommand extends Command
{
    protected $signature = 'app:test';

    public function __construct(private WclService $wclService)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $reportId = 'hk6zqmd7aFcjp2R3';

        // Завантажуємо всі зв'язки як це робить ProcessRaidAnalysisJob
        $static = StaticGroup::with('characters.user', 'members')->first();

        if (!$static) {
            $this->error('No static found.');
            return;
        }

        $rosterNames = $static->characters->pluck('name')->toArray();

        $this->info('Static: ' . $static->name);
        $this->info('Roster (' . count($rosterNames) . '): ' . implode(', ', $rosterNames));
        $this->info('Fetching WCL data...');

        $logData = $this->wclService->getLogSummary($reportId, $rosterNames);

        if (isset($logData['error'])) {
            $this->error($logData['error']);
            return;
        }

        // Зберігаємо складності окремо (як у job)
        $difficulties = $logData['difficulties'] ?? null;
        unset($logData['difficulties']);

        // Будуємо localization block
        $localization = $this->buildLocalization($static, $logData['players'] ?? []);

        // Фінальний payload — те що летить у Gemini
        $payload = [
            'localization' => $localization,
            'log_data'     => $logData,
        ];

        $outputPath = storage_path('logs/report_data_to_ai.json');
        file_put_contents($outputPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info('Saved to: ' . $outputPath);
        $this->line('');

        // --- Localization ---
        $this->info('=== LOCALIZATION ===');
        $this->line('raid_leader.locale: ' . $localization['raid_leader']['locale']);
        $this->info('participants:');
        foreach ($localization['participants'] as $p) {
            $this->line("  {$p['name']} → {$p['locale']}");
        }

        $this->line('');

        // --- Log Data keys ---
        $this->info('=== LOG DATA KEYS ===');
        $this->line(implode(', ', array_keys($logData)));
        $this->line('difficulties (stripped): ' . implode(', ', $difficulties ?? []));

        // --- Players ---
        $this->info('--- Players (' . count($logData['players'] ?? []) . ') ---');
        foreach ($logData['players'] ?? [] as $p) {
            $this->line("  {$p['name']} spec={$p['subType']}");
        }

        // --- Phase Summary ---
        $this->info('--- Phase Summary ---');
        foreach ($logData['phase_summary'] ?? [] as $boss => $fights) {
            foreach ($fights as $f) {
                $this->line("  {$boss} fight#{$f['fight_id']}: {$f['outcome']} phase={$f['last_phase']} dur={$f['duration_s']}s boss_pct={$f['boss_pct']}%");
            }
        }

        // --- Player Details ---
        $this->info('--- Player Details (' . count($logData['player_details'] ?? []) . ') ---');
        foreach ($logData['player_details'] ?? [] as $name => $d) {
            $trinkets = implode(', ', array_column($d['trinkets'] ?? [], 'name'));
            $this->line("  {$name}: role={$d['role']} class={$d['class']} spec={$d['spec']} ilvl={$d['avg_ilvl']} potion={$d['potion_use']}");
            $this->line("    trinkets: [{$trinkets}]");
            $this->line("    stats: " . json_encode($d['stats']));
        }

        // --- Performance Metrics ---
        $this->info('--- Performance Metrics ---');
        foreach ($logData['performance_metrics'] ?? [] as $name => $m) {
            $parse    = isset($m['parse_pct'])   ? " parse={$m['parse_pct']}%"   : '';
            $overheal = isset($m['overheal_pct']) ? " overheal={$m['overheal_pct']}%" : '';
            $this->line("  {$name}: spec={$m['spec']} dps=" . ($m['dps'] ?? 0) . " hps=" . ($m['hps'] ?? 0) . $parse . $overheal);
        }

        // --- Deaths ---
        $this->info('--- Deaths: ' . count($logData['deaths'] ?? []) . ' ---');
        foreach (array_slice($logData['deaths'] ?? [], 0, 5) as $d) {
            $this->line("  {$d['player']} fight#{$d['fight_id']} at {$d['time_into_fight']}: killed by {$d['killing_blow']}");
        }

        // --- Buff Uptime top 5 ---
        $this->info('--- Buff Uptime (top 5) ---');
        $i = 0;
        foreach ($logData['buff_uptime'] ?? [] as $name => $b) {
            if (++$i > 5) break;
            $this->line("  {$name}: {$b['uptime_pct']}% ({$b['total_uses']} uses)");
        }

        // --- Debuff Uptime top 5 ---
        $this->info('--- Debuff Uptime (top 5) ---');
        $i = 0;
        foreach ($logData['debuff_uptime'] ?? [] as $name => $b) {
            if (++$i > 5) break;
            $this->line("  {$name}: {$b['total_uptime_pct']}%");
        }

        // --- Misc ---
        $this->info('--- Fight Durations ---');
        $this->line('  total_seconds: ' . ($logData['fight_durations']['total_seconds'] ?? 0));
        $this->info('--- Interrupts: ' . count($logData['interrupts'] ?? []) . ' ---');
        $this->info('--- Major Damage Taken: ' . count($logData['major_damage_taken'] ?? []) . ' entries ---');
        $this->info('--- Resource Waste: ' . count($logData['resource_waste'] ?? []) . ' entries ---');
        $this->info('--- Consumables: ' . json_encode($logData['consumables_used']) . ' ---');

        $this->line('');
        $this->info('File size: ' . round(filesize($outputPath) / 1024, 1) . ' KB');
    }

    private function buildLocalization(\App\Models\StaticGroup $static, array $logPlayers): array
    {
        $leader = $static->members->first(
            fn($user) => $user->pivot->access_role === Role::Leader->value
        );
        $leaderLocale = Locale::fromString($leader?->locale ?? 'en')->fullName();

        $actualNames = array_column($logPlayers, 'name');

        $participants = $static->characters
            ->filter(fn($char) => in_array($char->name, $actualNames))
            ->map(fn($char) => [
                'name'   => $char->name,
                'locale' => Locale::fromString($char->user?->locale ?? 'en')->fullName(),
            ])
            ->values()
            ->toArray();

        return [
            'raid_leader'  => ['locale' => $leaderLocale],
            'participants' => $participants,
        ];
    }
}
