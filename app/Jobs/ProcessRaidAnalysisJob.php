<?php

namespace App\Jobs;

use App\Enums\Locale;
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

            // Завантажуємо characters з user для locale + members для лідера
            $static->load('characters.user', 'members');

            // Отримуємо масив імен з бази
            $rosterNames = $static->characters->pluck('name')->toArray();

            // Передаємо ростер для жорсткої фільтрації
            $logData = $wclService->getLogSummary($this->report->wcl_report_id, $rosterNames);

            // Зберігаємо складності одразу — вони вже відомі з WCL
            $difficulties = $logData['difficulties'] ?? null;
            unset($logData['difficulties']);

            // Будуємо локалізаційний блок
            $localization = $this->buildLocalization($static, $logData['players'] ?? []);

            // Відправляємо в Gemini (без поля difficulties — AI воно не потрібне)
            $aiJsonResponse = $geminiService->analyzeTacticalData(json_encode($logData), $localization);

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

            if ($this->report->event_id && $this->report->event) {
                $discordService->sendOrUpdateRaidAnnouncement($this->report->event);
            }

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
