<x-app-layout>
    <log-show
        :report='@json($reportData)'
        :personal-report='@json($personalData)'
        :roster-reports='@json($rosterReports)'
        :roster-members='@json($rosterMembers)'
        :is-raid-leader="{{ $canViewGlobalReport ? 'true' : 'false' }}"
        :can-view-global-report="{{ $canViewGlobalReport ? 'true' : 'false' }}"
        :can-use-ai-chat="{{ $canUseAiChat ? 'true' : 'false' }}"
        :chat-history='@json($chatHistory)'
        static-name="{{ $static->name }}"
        logs-index-url="{{ $logsIndexUrl }}"
        analyze-api-url="/api/logs/analyze"
    ></log-show>
</x-app-layout>
