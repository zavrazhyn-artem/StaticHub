<x-app-layout>
    <settings-schedule
        :schedule-data='@json($scheduleData)'
        update-url="{{ route('statics.settings.schedule.update') }}"
        profile-tab-url="{{ route('statics.settings.profile') }}"
        schedule-tab-url="{{ route('statics.settings.schedule') }}"
        discord-tab-url="{{ route('statics.settings.discord') }}"
        logs-tab-url="{{ route('statics.settings.logs') }}"
        :can-manage="true"
    ></settings-schedule>
</x-app-layout>
