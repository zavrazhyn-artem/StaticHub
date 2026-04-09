<x-app-layout>
    <settings-logs
        static-name="{{ $static->name }}"
        :guild-info='@json($guildInfo)'
        :auto-fetch-logs="{{ json_encode($autoFetchLogs) }}"
        :auto-fetch-delay-minutes="{{ $autoFetchDelayMinutes }}"
        update-url="{{ route('statics.settings.logs.update', $static) }}"
        connect-guild-url="{{ route('statics.settings.logs.connect-guild', $static) }}"
        disconnect-guild-url="{{ route('statics.settings.logs.disconnect-guild', $static) }}"
        profile-tab-url="{{ route('statics.settings.profile', $static) }}"
        schedule-tab-url="{{ route('statics.settings.schedule', $static) }}"
        discord-tab-url="{{ route('statics.settings.discord', $static) }}"
        logs-tab-url="{{ route('statics.settings.logs', $static) }}"
        :can-manage="true"
    ></settings-logs>
</x-app-layout>
