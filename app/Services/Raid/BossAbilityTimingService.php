<?php

declare(strict_types=1);

namespace App\Services\Raid;

use App\Models\BossAbilityTiming;
use App\Models\BossPhaseSegment;
use App\Services\Analysis\WclService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class BossAbilityTimingService
{
    public function __construct(
        private readonly WclService $wclService,
    ) {}

    /**
     * Seed boss ability timings for an encounter from a WCL report fight.
     * Returns a summary: how many abilities upserted, icons downloaded, etc.
     */
    public function seedFromWclReport(
        string $reportId,
        int $fightId,
        string $season,
        string $encounterSlug,
        string $difficulty = 'mythic',
        ?int $staticId = null,
        ?Carbon $sourceKillTime = null,
    ): array {
        $timeline = $this->wclService->fetchBossCastTimeline($reportId, $fightId);

        $iconDir = public_path('images/cooldowns');
        if (!File::isDirectory($iconDir)) {
            File::makeDirectory($iconDir, 0755, true);
        }

        $order = 0;
        $upserted = 0;
        $iconsDownloaded = 0;
        $now = Carbon::now();

        // Delete old rows for this exact slot (season + encounter + difficulty + static)
        $deleteScope = function ($q) use ($staticId) {
            if ($staticId === null) $q->whereNull('static_id');
            else $q->where('static_id', $staticId);
        };
        BossAbilityTiming::query()
            ->forSeasonEncounterDifficulty($season, $encounterSlug, $difficulty)
            ->where($deleteScope)
            ->delete();
        BossPhaseSegment::query()
            ->forSeasonEncounterDifficulty($season, $encounterSlug, $difficulty)
            ->where($deleteScope)
            ->delete();

        // Persist phase segments first (abilities reference segment_id).
        foreach ($timeline['segments'] ?? [] as $seg) {
            BossPhaseSegment::create([
                'season' => $season,
                'encounter_slug' => $encounterSlug,
                'difficulty' => $difficulty,
                'static_id' => $staticId,
                'segment_id' => $seg['segment_id'],
                'phase_id' => $seg['phase_id'],
                'phase_name' => $seg['phase_name'],
                'is_intermission' => $seg['is_intermission'],
                'seed_start' => $seg['seed_start'],
                'seed_duration' => $seg['seed_duration'],
                'segment_order' => $seg['segment_order'],
                'source_report_code' => $reportId,
                'source_fight_id' => $fightId,
                'seeded_at' => $now,
            ]);
        }

        foreach ($timeline['abilities'] as $ability) {
            // Skip abilities with no casts (shouldn't happen, defensive)
            if (empty($ability['casts'])) continue;

            $spellId = (int) $ability['spell_id'];
            $iconName = $ability['icon'] ?: null;
            $iconFilename = null;

            if ($iconName) {
                $localPath = $iconDir . '/' . $spellId . '.jpg';
                if (!File::exists($localPath)) {
                    $downloaded = $this->downloadIcon($iconName, $localPath);
                    if ($downloaded) $iconsDownloaded++;
                }
                if (File::exists($localPath)) {
                    $iconFilename = $spellId . '.jpg';
                }
            }

            BossAbilityTiming::create([
                'season' => $season,
                'encounter_slug' => $encounterSlug,
                'difficulty' => $difficulty,
                'static_id' => $staticId,
                'spell_id' => $spellId,
                'name' => $ability['name'],
                'icon_filename' => $iconFilename,
                'color' => $this->colorForAbility($ability['name'], (int) $ability['type']),
                'ability_type' => $this->spellSchoolLabel((int) $ability['type']),
                'default_casts' => $ability['casts'],
                'duration_sec' => (int) ($ability['duration_sec'] ?? 0),
                'row_order' => $order++,
                'source_report_code' => $reportId,
                'source_fight_id' => $fightId,
                'source_kill_time' => $sourceKillTime,
                'seeded_at' => $now,
            ]);

            $upserted++;
        }

        return [
            'encounter' => $encounterSlug,
            'fight' => $timeline['fight'],
            'segments' => $timeline['segments'] ?? [],
            'abilities_upserted' => $upserted,
            'icons_downloaded' => $iconsDownloaded,
        ];
    }

    /**
     * Auto-discover all encounters in a WCL zone and seed them for all 3 raid
     * difficulties (normal, heroic, mythic) using each difficulty's top speed kill.
     * Used by `cooldowns:seed-season` command and the weekly cron.
     */
    public function seedSeasonGlobal(string $season, int $zoneId, ?string $onlyEncounterSlug = null): array
    {
        $encounters = $this->wclService->fetchZoneEncounters($zoneId);
        $difficulties = [
            3 => 'normal',
            4 => 'heroic',
            5 => 'mythic',
        ];

        $report = [
            'zone_id' => $zoneId,
            'season' => $season,
            'encounters' => [],
        ];

        foreach ($encounters as $enc) {
            $slug = Str::slug($enc['name']);
            if ($onlyEncounterSlug && $slug !== $onlyEncounterSlug) continue;

            $encReport = ['name' => $enc['name'], 'slug' => $slug, 'difficulties' => []];

            foreach ($difficulties as $diffCode => $diffLabel) {
                try {
                    $top = $this->wclService->findTopKillFightForEncounter((int) $enc['id'], $diffCode);
                } catch (\Throwable $e) {
                    $encReport['difficulties'][$diffLabel] = ['status' => 'error', 'message' => $e->getMessage()];
                    continue;
                }

                if (!$top) {
                    $encReport['difficulties'][$diffLabel] = ['status' => 'no_rankings'];
                    continue;
                }

                try {
                    $killTime = isset($top['kill_started_at_ms'])
                        ? Carbon::createFromTimestampMs($top['kill_started_at_ms'])
                        : null;

                    $result = $this->seedFromWclReport(
                        reportId: $top['report_code'],
                        fightId: $top['fight_id'],
                        season: $season,
                        encounterSlug: $slug,
                        difficulty: $diffLabel,
                        staticId: null,
                        sourceKillTime: $killTime,
                    );
                    $encReport['difficulties'][$diffLabel] = [
                        'status' => 'ok',
                        'abilities' => $result['abilities_upserted'],
                        'icons' => $result['icons_downloaded'],
                        'source' => $top['report_code'] . '#' . $top['fight_id'],
                    ];
                } catch (\Throwable $e) {
                    $encReport['difficulties'][$diffLabel] = ['status' => 'error', 'message' => $e->getMessage()];
                }
            }

            $report['encounters'][] = $encReport;
        }

        return $report;
    }

    private function downloadIcon(string $iconName, string $localPath): bool
    {
        $url = "https://wow.zamimg.com/images/wow/icons/large/{$iconName}.jpg";
        try {
            $response = Http::timeout(15)->get($url);
            if ($response->successful() && $response->body() !== '') {
                File::put($localPath, $response->body());
                return true;
            }
        } catch (\Throwable) {
            // silent — missing icon just leaves DB row without icon_filename
        }
        return false;
    }

    /**
     * Pick a stable, visually distinct color per ability. Uses spell school as a
     * hint to bias toward a plausible hue, then offsets by a hash of the name so
     * multiple abilities from the same school still look different on the timeline.
     */
    private function colorForAbility(string $name, int $mask): string
    {
        $palettes = [
            'shadow'   => ['#A78BFA', '#8B5CF6', '#C084FC', '#7C3AED', '#6D28D9', '#DDD6FE'],
            'fire'     => ['#F97316', '#FB923C', '#EA580C', '#FCA5A5', '#DC2626', '#FBBF24'],
            'frost'    => ['#06B6D4', '#38BDF8', '#22D3EE', '#0EA5E9', '#7DD3FC', '#0284C7'],
            'nature'   => ['#22C55E', '#4ADE80', '#16A34A', '#84CC16', '#A3E635', '#65A30D'],
            'arcane'   => ['#EC4899', '#F472B6', '#DB2777', '#F9A8D4', '#E879F9', '#D946EF'],
            'holy'     => ['#FACC15', '#FDE047', '#EAB308', '#FBBF24', '#FEF3C7', '#CA8A04'],
            'physical' => ['#F5F5F4', '#E5E5E5', '#D4D4D8', '#A8A29E', '#FAFAFA', '#78716C'],
        ];

        $school = match (true) {
            ($mask & 32) > 0 => 'shadow',
            ($mask & 4)  > 0 => 'fire',
            ($mask & 16) > 0 => 'frost',
            ($mask & 8)  > 0 => 'nature',
            ($mask & 64) > 0 => 'arcane',
            ($mask & 2)  > 0 => 'holy',
            ($mask & 1)  > 0 => 'physical',
            default          => 'shadow',
        };

        $palette = $palettes[$school];
        $idx = crc32($name) % count($palette);
        return $palette[$idx];
    }

    private function spellSchoolLabel(int $mask): string
    {
        $parts = [];
        if (($mask & 1)  > 0) $parts[] = 'Physical';
        if (($mask & 2)  > 0) $parts[] = 'Holy';
        if (($mask & 4)  > 0) $parts[] = 'Fire';
        if (($mask & 8)  > 0) $parts[] = 'Nature';
        if (($mask & 16) > 0) $parts[] = 'Frost';
        if (($mask & 32) > 0) $parts[] = 'Shadow';
        if (($mask & 64) > 0) $parts[] = 'Arcane';
        return implode('+', $parts) ?: 'Unknown';
    }
}
