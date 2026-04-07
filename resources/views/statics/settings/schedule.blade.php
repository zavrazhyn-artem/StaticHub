<x-app-layout>
    <settings-schedule
        :schedule-data='@json($scheduleData)'
        update-url="{{ route('statics.settings.schedule.update', $static) }}"
        schedule-tab-url="{{ route('statics.settings.schedule', $static) }}"
        discord-tab-url="{{ route('statics.settings.discord', $static) }}"
        logs-tab-url="{{ route('statics.settings.logs', $static) }}"
    ></settings-schedule>
</x-app-layout>
