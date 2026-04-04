<?php

namespace App\Jobs;

use App\Models\PersonalTacticalReport;
use App\Models\TacticalReport;
use App\Services\Discord\DiscordMessageService;
use App\Services\Analysis\GeminiService;
use App\Services\Analysis\WclService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessRaidAnalysisJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public TacticalReport $report;

    public function __construct(TacticalReport $report)
    {
        $this->report = $report;
        $this->onQueue('ai');
    }

    public function handle(WclService $wclService, GeminiService $geminiService, DiscordMessageService $discordService): void
    {
        if (!$this->report->wcl_report_id) return;

        try {
            $static = $this->report->staticGroup;
            // Отримуємо масив імен з бази
            $rosterNames = $static->characters->pluck('name')->toArray();

            // Передаємо ростер для жорсткої фільтрації
            $logData = $wclService->getLogSummary($this->report->wcl_report_id, $rosterNames);

            // Зберігаємо складності одразу — вони вже відомі з WCL
            $difficulties = $logData['difficulties'] ?? null;
            unset($logData['difficulties']);

            // Відправляємо в Gemini (без поля difficulties — AI воно не потрібне)
            $aiJsonResponse = $geminiService->analyzeTacticalData(json_encode($logData));

            // Розбираємо отриманий JSON
            $parsedData = json_decode($aiJsonResponse, true);

            if (!$parsedData || !is_array($parsedData)) {
                Log::error("Gemini didn't return valid JSON. Raw response: " . $aiJsonResponse);
                return;
            }

            // 1. Зберігаємо загальний звіт та мета-поля
            $this->report->update([
                'title'        => $parsedData['title'] ?? $logData['raid_title'] ?? $this->report->title ?? 'Raid Analysis',
                'difficulties' => $difficulties,
                'ai_analysis'  => $parsedData['main'] ?? 'Analysis not generated.',
            ]);

            // 2. Зберігаємо особисті звіти (всі інші ключі, крім мета-полів)
            $metaKeys = ['title', 'main'];
            $rosterCharacters = $static->characters;

            foreach ($parsedData as $playerName => $content) {
                if (in_array($playerName, $metaKeys, true)) continue;

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

            if ($this->report->raid_event_id && $this->report->raidEvent) {
                $discordService->sendOrUpdateRaidAnnouncement($this->report->raidEvent);
            }

        } catch (\Exception $e) {
            Log::error("ProcessRaidAnalysisJob failed: " . $e->getMessage());
            throw $e;
        }
    }
}
