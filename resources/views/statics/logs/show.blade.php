<x-app-layout>
    @php
        // Render AI analysis markdown server-side
        $aiHtml = null;
        if ($report->ai_analysis) {
            $rawText = $report->ai_analysis;
            if (\Illuminate\Support\Str::startsWith($rawText, '"') && \Illuminate\Support\Str::endsWith($rawText, '"')) {
                $rawText = json_decode($rawText);
            }
            $cleanText = str_replace(['\n', '\r'], ["\n", "\r"], $rawText);
            $aiHtml = \Illuminate\Support\Str::markdown($cleanText);
        }

        // Execution metrics (placeholder values, same as before)
        $executionRank    = rand(60, 95);
        $avoidableDamage  = rand(10, 40);
        $executionMetrics = [
            [
                'label'     => 'Execution Rank',
                'value'     => $executionRank,
                'color'     => 'text-amber-500',
                'bar_class' => 'bg-amber-500 shadow-[0_0_10px_rgba(245,158,11,0.5)]',
                'note'      => null,
            ],
            [
                'label'     => 'Avoidable DMG',
                'value'     => $avoidableDamage,
                'color'     => 'text-error',
                'bar_class' => 'bg-error shadow-[0_0_10px_rgba(239,68,68,0.5)]',
                'note'      => 'Compared to regional average',
            ],
        ];

        // Personal report
        $isRaidLeader = auth()->check() && (
            $static->owner_id === auth()->id() ||
            $static->members()->where('user_id', auth()->id())->wherePivot('role', 'Raid Leader')->exists()
        );
        $authUserReport = $userCharacter
            ? $report->personalReports()->where('character_id', $userCharacter->id)->first()
            : null;

        $personalData = null;
        if ($authUserReport) {
            $char = $authUserReport->character;
            $personalData = [
                'html'           => \Illuminate\Support\Str::markdown($authUserReport->content),
                'char_name'      => $char->name,
                'char_class'     => $char->playable_class,
                'char_class_css' => strtolower(str_replace(' ', '-', $char->playable_class)),
            ];
        }

        $reportData = [
            'id'               => $report->id,
            'title'            => $report->title ?? 'Manual Log Analysis',
            'wcl_report_id'    => $report->wcl_report_id,
            'created_at'       => $report->created_at->format('F d, Y @ H:i'),
            'has_ai_analysis'  => (bool) $report->ai_analysis,
            'ai_html'          => $aiHtml,
            'duration_hours'   => $report->raidEvent
                ? $report->raidEvent->start_time->diffInHours($report->raidEvent->end_time)
                : null,
            'wcl_url'          => 'https://www.warcraftlogs.com/reports/' . $report->wcl_report_id,
            'execution_metrics' => $executionMetrics,
        ];
    @endphp

    <log-show
        :report='@json($reportData)'
        :personal-report='@json($personalData)'
        :is-raid-leader="{{ $isRaidLeader ? 'true' : 'false' }}"
        static-name="{{ $static->name }}"
        logs-index-url="{{ route('statics.logs.index', $static) }}"
        analyze-api-url="/api/logs/analyze"
    ></log-show>
</x-app-layout>
