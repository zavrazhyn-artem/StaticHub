<x-app-layout>
    @php
        $canManageSchedule = Auth::user()->can('canManageSchedule', $static);
    @endphp

    <div id="app">
        <schedule-calendar
            :static-id="{{ $static->id }}"
            static-name="{{ $static->name }}"
            :can-manage-schedule="{{ $canManageSchedule ? 'true' : 'false' }}"
            default-raid-time="{{ $static->raid_start_time ? \Carbon\Carbon::parse($static->raid_start_time)->format('H:i') : '20:00' }}"
            default-raid-end-time="{{ $static->raid_end_time ? \Carbon\Carbon::parse($static->raid_end_time)->format('H:i') : '23:00' }}"
            static-timezone="{{ $static->timezone ?? 'UTC' }}"
            current-month-name="{{ $current_date->format('F Y') }}"
            prev-month-url="{{ route('schedule.index', ['year' => $prev_month->year, 'month' => $prev_month->month]) }}"
            next-month-url="{{ route('schedule.index', ['year' => $next_month->year, 'month' => $next_month->month]) }}"
            today-url="{{ route('schedule.index') }}"
            :grid='@json($mappedGrid)'
            :errors='@json($errors->getMessages())'
            csrf-token="{{ csrf_token() }}"
            create-event-route="{{ route('schedule.store') }}"
            @if ($canManageSchedule)
                settings-route="{{ route('statics.settings.schedule', $static) }}"
            @endif
        ></schedule-calendar>
    </div>
</x-app-layout>
