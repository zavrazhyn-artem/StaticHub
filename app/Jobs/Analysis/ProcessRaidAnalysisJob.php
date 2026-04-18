<?php

namespace App\Jobs\Analysis;

use App\Enums\Locale;
use App\Helpers\DiscordWebhookBuilder;
use App\Models\PersonalTacticalReport;
use App\Models\TacticalReport;
use App\Services\Analysis\GeminiService;
use App\Services\Analysis\TacticalDataAnalyzer;
use App\Services\Analysis\WclService;
use App\Services\Discord\DiscordWebhookService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessRaidAnalysisJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $timeout = 600;
    public int $uniqueFor = 900;
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
        DiscordWebhookService $webhookService
    ): void {
        if (!$this->report->wcl_report_id) return;

        // Guard against duplicate runs — if the report already has AI analysis + valid cache,
        // treat subsequent dispatches as no-ops instead of re-running the full pipeline.
        $this->report->refresh();
        if ($this->report->ai_analysis && $this->report->isCacheActive()) {
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
            $preprocessedJson = json_encode($preprocessed, JSON_UNESCAPED_UNICODE);

            // Player details + raid-wide consumables stay raid-wide (don't change per encounter)
            $supplementary = json_encode([
                'player_details' => $logData['player_details'] ?? [],
            ], JSON_UNESCAPED_UNICODE);

            // Stage 3: Pro report generation (with cache; per-encounter stats are inside preprocessed.encounters[])
            $aiResult = $geminiService->generateReportFromPreprocessed($preprocessedJson, $supplementary);

            $aiJsonResponse = $aiResult['response'];
            $reportModel = $aiResult['model'];
            $cacheId = $aiResult['cache_id'] ?? null;
            $cacheExpiresAt = $aiResult['cache_expires_at'] ?? null;

            // Розбираємо отриманий JSON
            $parsedData = json_decode($aiJsonResponse, true);

            if (!$parsedData || !is_array($parsedData)) {
                Log::error("Gemini didn't return valid JSON. Raw response: " . $aiJsonResponse);
                return;
            }

            // 1. Зберігаємо загальний звіт та мета-поля
            $this->report->update([
                'title'                  => $parsedData['title'] ?? $logData['raid_title'] ?? $this->report->title ?? 'Raid Analysis',
                'difficulties'           => $difficulties,
                'ai_analysis'            => $parsedData['main'] ?? 'Analysis not generated.',
                'model'                  => $reportModel,
                'gemini_cache_id'        => $cacheId,
                'gemini_cache_expires_at' => $cacheExpiresAt ? \Carbon\Carbon::parse($cacheExpiresAt) : null,
            ]);

            // 2. Зберігаємо особисті звіти тільки для учасників рейду з ростеру
            $metaKeys = ['title', 'main'];
            $rosterCharacters = $static->characters;
            $actualParticipantNames = array_map('strtolower', array_column($logData['players'] ?? [], 'name'));

            foreach ($parsedData as $playerName => $content) {
                if (in_array($playerName, $metaKeys, true)) continue;
                if (!in_array(strtolower(trim($playerName)), $actualParticipantNames)) continue;

                $character = $rosterCharacters->first(function ($c) use ($playerName) {
                    return strtolower($c->name) === strtolower(trim($playerName));
                });

                if ($character && !empty($content)) {
                    PersonalTacticalReport::updateOrCreate(
                        [
                            'tactical_report_id' => $this->report->id,
                            'character_id' => $character->id,
                        ],
                        ['content' => $content]
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
