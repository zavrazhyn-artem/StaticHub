<x-app-layout>
    <settings-schedule
        :schedule-data='@json($scheduleData)'
        update-url="{{ route('statics.settings.schedule.update', $static) }}"
        profile-tab-url="{{ route('statics.settings.profile', $static) }}"
        schedule-tab-url="{{ route('statics.settings.schedule', $static) }}"
        discord-tab-url="{{ route('statics.settings.discord', $static) }}"
        logs-tab-url="{{ route('statics.settings.logs', $static) }}"
        :can-manage="true"
    ></settings-schedule>
</x-app-layout>
