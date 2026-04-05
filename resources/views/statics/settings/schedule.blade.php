<x-app-layout>
    @php
        $scheduleData = [
            'static_name'           => $static->name,
            'raid_days'             => is_array($static->raid_days)
                ? $static->raid_days
                : (json_decode($static->raid_days, true) ?? []),
            'raid_start_time'       => $static->raid_start_time
                ? \Carbon\Carbon::parse($static->raid_start_time)->format('H:i')
                : '',
            'raid_end_time'         => $static->raid_end_time
                ? \Carbon\Carbon::parse($static->raid_end_time)->format('H:i')
                : '',
            'timezone'              => $static->timezone ?? 'Europe/Paris',
            'weekly_tax_per_player' => (int) (($static->weekly_tax_per_player ?? 0) / 10000),
            'automation_settings'   => $static->automation_settings ?? [],
        ];
    @endphp

    <settings-schedule
        :schedule-data='@json($scheduleData)'
        update-url="{{ route('statics.settings.schedule.update', $static) }}"
        schedule-tab-url="{{ route('statics.settings.schedule', $static) }}"
        discord-tab-url="{{ route('statics.settings.discord', $static) }}"
        logs-tab-url="{{ route('statics.settings.logs', $static) }}"
        success-message="{{ session('success', '') }}"
    ></settings-schedule>
</x-app-layout>
