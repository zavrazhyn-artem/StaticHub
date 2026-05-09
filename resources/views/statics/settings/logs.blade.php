<x-app-layout>
    <settings-logs
        static-name="{{ $static->name }}"
        :guild-info='@json($guildInfo)'
        :auto-fetch-logs="{{ json_encode($autoFetchLogs) }}"
        :auto-fetch-delay-minutes="{{ $autoFetchDelayMinutes }}"
        ai-tone="{{ $aiTone }}"
        :ai-death-cutoff="{{ $aiDeathCutoff }}"
        update-url="{{ route('statics.settings.logs.update') }}"
        connect-guild-url="{{ route('statics.settings.logs.connect-guild') }}"
        disconnect-guild-url="{{ route('statics.settings.logs.disconnect-guild') }}"
        profile-tab-url="{{ route('statics.settings.profile') }}"
        schedule-tab-url="{{ route('statics.settings.schedule') }}"
        discord-tab-url="{{ route('statics.settings.discord') }}"
        logs-tab-url="{{ route('statics.settings.logs') }}"
        wishlist-configs-tab-url="{{ route('statics.settings.wishlist-configs') }}"
        :can-manage="true"
    ></settings-logs>
</x-app-layout>
