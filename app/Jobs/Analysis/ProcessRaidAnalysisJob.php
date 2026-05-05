<?php

namespace App\Jobs\Analysis;

use App\Enums\Locale;
use App\Helpers\DiscordWebhookBuilder;
use App\Models\PersonalTacticalReport;
use App\Models\TacticalReport;
use App\Services\Analysis\AiDeathSuppressor;
use App\Services\Analysis\BlockSchema;
use App\Services\Analysis\EncounterSnapshotService;
use App\Services\Analysis\GeminiService;
use App\Services\Analysis\RaidPayloadStorage;
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

    public int $timeout = 2400;
    public int $uniqueFor = 3000;
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
        DiscordWebhookService $webhookService,
        RaidPayloadStorage $payloadStorage,
        AiDeathSuppressor $deathSuppressor
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

            // AI-only filter: cascade-suppress deaths in the last 10s of each pull,
            // cap to first N (per-static setting), drop pulls < 20s. Snapshots and
            // trends above use the unfiltered $preprocessed.
            $aiPreprocessed = $deathSuppressor->suppress(
                $preprocessed,
                (int) ($static->ai_death_cutoff ?? 3)
            );

            $preprocessedJson = json_encode($aiPreprocessed, JSON_UNESCAPED_UNICODE);

            // Player details + raid-wide consumables stay raid-wide (don't change per encounter)
            $supplementary = json_encode([
                'player_details' => $logData['player_details'] ?? [],
            ], JSON_UNESCAPED_UNICODE);

            // Stage 3a: build the stable context block + persist to disk so chat
            // can recreate an explicit cache on demand.
            $contextContent = $geminiService->buildRaidContextContent($preprocessedJson, $supplementary);
            $payloadStorage->store($this->report->id, $contextContent);

            Log::info('Raid payload stored for chat reactivation', [
                'report_id'    => $this->report->id,
                'context_size' => strlen($contextContent),
            ]);

            // Stage 3a.5: explicit cache scoped to this generation run. Preview-tier
            // models have a tight TPM bucket — sending the full ~290K-token context
            // inline 17 times forced ~40s-per-call recovery and stretched the job to
            // 15 minutes. Caching the prefix server-side cuts each per-call payload
            // to ~10K (instructions only) and lifts the TPM ceiling. Storage cost is
            // ~$1/M-tokens-stored × ~5 minutes ≈ a few cents per raid — far below
            // the time savings.
            $tone = $static->ai_tone ?? 'neutral';
            $genCache = $geminiService->createRaidCache($contextContent, 600);
            $genCacheId = $genCache['cache_id'] ?? null;
            if ($genCacheId) {
                Log::info('Generation cache created', [
                    'report_id'  => $this->report->id,
                    'cache_id'   => $genCacheId,
                    'expires_at' => $genCache['expires_at'] ?? null,
                ]);
            } else {
                Log::warning('Generation cache creation failed — falling back to inline path', [
                    'report_id' => $this->report->id,
                ]);
            }

            try {
                // Stage 3b: main raid-wide report
                $raidLeaderLocale = $localization['raid_leader']['locale'] ?? 'English';
                $main = $geminiService->generateMainReportBlocks($contextContent, $tone, $genCacheId, $raidLeaderLocale);
                $mainBlocks = $blockSchema->sanitize($main['main']);
                $title = $main['title'] ?: ($logData['raid_title'] ?? $this->report->title ?? 'Raid Analysis');

                $this->report->update([
                    'title'                   => $title,
                    'difficulties'            => $difficulties,
                    'ai_blocks'               => $mainBlocks,
                    'model'                   => config('services.gemini.pro_model'),
                    'prompt_version'          => (string) config('ai_report.prompt_version', 'v1'),
                    'format_version'          => 2,
                    'gemini_cache_id'         => null,
                    'gemini_cache_expires_at' => null,
                ]);

                // Stage 3c: per-player reports (parallel via Http::pool)
                $rosterCharacters = $static->characters;
                $actualParticipantNames = array_column($logData['players'] ?? [], 'name');

                // Only generate for players who are in BOTH the log AND the roster
                $targetPlayers = array_values(array_filter(
                    $actualParticipantNames,
                    fn($name) => $rosterCharacters->contains(fn($c) => strtolower($c->name) === strtolower(trim($name)))
                ));

                if (!empty($targetPlayers)) {
                    $concurrency = $genCacheId ? 5 : 1;
                    $reportId = $this->report->id;

                    // Pre-resolved per-player locale map. Without this the
                    // model has to look up the player in the cached
                    // localization map and ~19% picked the wrong language.
                    $playerLocales = [];
                    foreach ($localization['participants'] ?? [] as $entry) {
                        if (!empty($entry['name']) && !empty($entry['locale'])) {
                            $playerLocales[$entry['name']] = $entry['locale'];
                        }
                    }

                    // Stream-save each successful personal report the moment
                    // its API call returns. If the job hits its timeout while
                    // the rest are still rate-limit-waiting, the saved ones
                    // survive — earlier behaviour lost everything when timeout
                    // hit during the batch loop.
                    $onPlayerComplete = function (string $playerName, array $blocks) use (
                        $rosterCharacters, $blockSchema, $reportId
                    ): void {
                        if (empty($blocks)) return;

                        $character = $rosterCharacters->first(
                            fn($c) => strtolower($c->name) === strtolower(trim($playerName))
                        );
                        if (!$character) return;

                        $sanitized = $blockSchema->sanitize($blocks);
                        PersonalTacticalReport::updateOrCreate(
                            [
                                'tactical_report_id' => $reportId,
                                'character_id'       => $character->id,
                            ],
                            ['ai_blocks' => $sanitized]
                        );
                    };

                    $geminiService->generatePlayerReportBlocks(
                        $contextContent,
                        $targetPlayers,
                        $concurrency,
                        $tone,
                        $genCacheId,
                        $onPlayerComplete,
                        $playerLocales
                    );
                }
            } finally {
                // Always release the generation cache — chat creates its own
                // explicit cache via AiAnalystService::activateChat().
                if ($genCacheId) {
                    $geminiService->deleteCachedContext($genCacheId);
                    Log::info('Generation cache released', [
                        'report_id' => $this->report->id,
                        'cache_id'  => $genCacheId,
                    ]);
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
