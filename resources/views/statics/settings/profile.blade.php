<x-app-layout>
    <settings-profile
        static-name="{{ $static->name }}"
        :discord='@json($discord)'
        :statics='@json($statics)'
        :transfer-data='@json($transferData)'
        :privacy='@json($privacy)'
        discord-link-url="{{ route('profile.discord.link') }}"
        discord-unlink-url="{{ route('profile.discord.unlink') }}"
        leave-static-url="{{ route('profile.static.leave') }}"
        profile-tab-url="{{ route('statics.settings.profile') }}"
        schedule-tab-url="{{ route('statics.settings.schedule') }}"
        discord-tab-url="{{ route('statics.settings.discord') }}"
        logs-tab-url="{{ route('statics.settings.logs') }}"
        :can-manage="{{ json_encode($canManage) }}"
        :ownership-transferred="{{ json_encode(session('status') === 'ownership-transferred') }}"
    ></settings-profile>
</x-app-layout>
