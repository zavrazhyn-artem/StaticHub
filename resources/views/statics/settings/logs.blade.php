<x-app-layout>
    <settings-logs
        static-name="{{ $static->name }}"
        wcl-guild-id="{{ $static->wcl_guild_id ?? '' }}"
        wcl-region="{{ $static->wcl_region ?? '' }}"
        wcl-realm="{{ $static->wcl_realm ?? $static->server ?? '' }}"
        update-url="{{ route('statics.settings.logs.update', $static) }}"
        schedule-tab-url="{{ route('statics.settings.schedule', $static) }}"
        discord-tab-url="{{ route('statics.settings.discord', $static) }}"
        logs-tab-url="{{ route('statics.settings.logs', $static) }}"
        success-message="{{ session('success', '') }}"
    ></settings-logs>
</x-app-layout>
