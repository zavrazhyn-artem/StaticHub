<?php

namespace App\Jobs\Analysis;

use App\Enums\Locale;
use App\Helpers\DiscordWebhookBuilder;
use App\Models\PersonalTacticalReport;
use App\Models\TacticalReport;
use App\Services\Analysis\BlockSchema;
use App\Services\Analysis\EncounterSnapshotService;
use App\Services\Analysis\GeminiService;
use App\Services\Analysis\TacticalDataAnalyzer;
use App\Services\Analysis\TrendAnalyzer;
use App\Services\Analysis\WclService;
use App\Services\Discord\DiscordWebhookService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessRaidAnalysisJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $timeout = 1200;
    public int $uniqueFor = 1800;
    public int $tries = 1;
    public int $backoff = 60;

    public TacticalReport $report;

    public function __construct(TacticalReport $report)
    {
        $this->report = $report;
        $this->onQueue('ai');
    }

    public function uniqueId(): string
    {
        return (string) $this->report->id;
    }

    public function handle(
        WclService $wclService,
        TacticalDataAnalyzer $analyzer,
        GeminiService $geminiService,
        BlockSchema $blockSchema,
        EncounterSnapshotService $snapshotService,
        TrendAnalyzer $trendAnalyzer,
        DiscordWebhookService $webhookService
    ): void {
        // Large preprocessed JSON + Gemini HTTP pool responses can briefly exceed
        // the default worker memory ceiling; cap within the job to stay well under
        // supervisor's --memory=1024 budget while leaving headroom for PHP itself.
        ini_set('memory_limit', '896M');

        if (!$this->report->wcl_report_id) return;

        // Guard against duplicate runs — if the report already has AI output + valid cache,
        // treat subsequent dispatches as no-ops instead of re-running the full pipeline.
        $this->report->refresh();
        $hasOutput = $this->report->ai_blocks || $this->report->ai_analysis;
        if ($hasOutput && $this->report->isCacheActive()) {
            Log::info("ProcessRaidAnalysisJob skipped — report already processed", [
                'report_id' => $this->report->id,
            ]);
            return;
        }

        try {
            $static = $this->report->staticGroup;
            $static->load('characters.user', 'members');
            $rosterNames = $static->characters->pluck('name')->toArray();

            // Stage 1: WCL fetch (with roster filter)
            $logData = $wclService->getLogSummary($this->report->wcl_report_id, $rosterNames);
            $difficulties = $logData['difficulties'] ?? null;

            $localization = $this->buildLocalization($static, $logData['players'] ?? []);

            // Stage 2: PHP TacticalDataAnalyzer (deterministic preprocessing — replaces Flash)
            $preprocessed = $analyzer->analyze(
                $logData,
                $localization,
                $rosterNames,
                $this->report->wcl_report_id
            );

            // Wave 3: persist per-encounter snapshots (always, regardless of subscription)
            // and append cross-raid trends ONLY if the static has premium tier (feature flag).
            try {
                $snapshotService->saveFromPreprocessed($this->report, $preprocessed);
            } catch (\Throwable $e) {
                Log::warning('Snapshot save failed (non-fatal): ' . $e->getMessage());
            }

            if ($this->trendsEnabled($static)) {
                try {
                    $trends = $trendAnalyzer->buildTrends(
                        $static->id,
                        $this->report->id,
                        $preprocessed['encounters'] ?? []
                    );
                    if ($trends['enabled']) {
                        $preprocessed['cross_raid_trends'] = $trends;
                    }
                } catch (\Throwable $e) {
                    Log::warning('Trend build failed (non-fatal): ' . $e->getMessage());
                }
            }

            $preprocessedJson = json_encode($preprocessed, JSON_UNESCAPED_UNICODE);

            // Player details + raid-wide consumables stay raid-wide (don't change per encounter)
            $supplementary = json_encode([
                'player_details' => $logData['player_details'] ?? [],
            ], JSON_UNESCAPED_UNICODE);

            // Stage 3a: create shared cache once (reused by main + per-player generators + chat)
            $cache = $geminiService->createRaidCache($preprocessedJson, $supplementary);
            $cacheId = $cache['cache_id'] ?? null;
            $cacheExpiresAt = $cache['expires_at'] ?? null;

            if (!$cacheId) {
                throw new \Exception('Gemini cache creation failed — cannot proceed with split generation');
            }

            // Stage 3b: main raid-wide report (single call)
            $main = $geminiService->generateMainReportBlocks($cacheId);
            $mainBlocks = $blockSchema->sanitize($main['main']);
            $title = $main['title'] ?: ($logData['raid_title'] ?? $this->report->title ?? 'Raid Analysis');

            $this->report->update([
                'title'                  => $title,
                'difficulties'           => $difficulties,
                'ai_blocks'              => $mainBlocks,
                'model'                  => config('services.gemini.pro_model'),
                'gemini_cache_id'        => $cacheId,
                'gemini_cache_expires_at' => $cacheExpiresAt ? \Carbon\Carbon::parse($cacheExpiresAt) : null,
            ]);

            // Stage 3c: per-player reports (parallel batches via Http::pool)
            $rosterCharacters = $static->characters;
            $actualParticipantNames = array_column($logData['players'] ?? [], 'name');

            // Only generate for players who are in BOTH the log AND the roster
            $targetPlayers = array_values(array_filter(
                $actualParticipantNames,
                fn($name) => $rosterCharacters->contains(fn($c) => strtolower($c->name) === strtolower(trim($name)))
            ));

            if (!empty($targetPlayers)) {
                $playerReports = $geminiService->generatePlayerReportBlocks($cacheId, $targetPlayers, 5);

                foreach ($playerReports as $playerName => $blocks) {
                    if (empty($blocks)) continue;

                    $character = $rosterCharacters->first(
                        fn($c) => strtolower($c->name) === strtolower(trim($playerName))
                    );
                    if (!$character) continue;

                    $sanitized = $blockSchema->sanitize($blocks);
                    PersonalTacticalReport::updateOrCreate(
                        [
                            'tactical_report_id' => $this->report->id,
                            'character_id'       => $character->id,
                        ],
                        ['ai_blocks' => $sanitized]
                    );
                }
            }

            // Send webhook notification that AI report is ready
            $reportTitle = $this->report->title ?? 'Raid Analysis';
            $reportUrl = route('statics.logs.show', $this->report);
            $payload = DiscordWebhookBuilder::buildAnalysisReadyPayload($reportTitle, $reportUrl);
            $webhookService->sendNotification($static, $payload);

        } catch (\Exception $e) {
            Log::error("ProcessRaidAnalysisJob failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Cross-raid trends are a premium-tier feature. Until the subscription system is
     * wired up, we gate behind a config flag so dev / preview environments can opt in.
     */
    private function trendsEnabled(\App\Models\StaticGroup $static): bool
    {
        // TODO: replace with subscription tier check (e.g. $static->subscription?->tier === 'elite')
        return (bool) config('analysis.cross_raid_trends_enabled', true);
    }

    /**
     * Build the localization block for the AI prompt.
     * Only includes participants who actually appeared in the WCL log.
     *
     * @param \App\Models\StaticGroup $static
     * @param array $logPlayers  — players array from getLogSummary (filtered roster)
     * @return array{raid_leader: array, participants: array}
     */
    private function buildLocalization(\App\Models\StaticGroup $static, array $logPlayers): array
    {
        // Raid leader: the single member with access_role = 'leader'
        $leader = $static->members->first(
            fn($user) => $user->pivot->access_role === \App\Enums\StaticGroup\Role::Leader->value
        );
        $leaderLocale = Locale::fromString($leader?->locale ?? 'en')->fullName();

        // Only participants who actually showed up in the log
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
