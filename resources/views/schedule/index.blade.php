<x-app-layout>
    @php
        $mappedGrid = collect($grid)->map(fn($day) => [
            "day_number" => $day["date"]->format("j"),
            "formatted_date" => $day["date"]->format("Y-m-d"),
            "is_today" => $day["is_today"],
            "is_current_month" => $day["is_current_month"],
            "events" => collect($day["events"])->map(fn($event) => [
                "id" => $event->id,
                "show_url" => route("schedule.event.show", $event),
                "update_url" => route("schedule.event.update", $event),
                "start_time" => $event->start_time->toIso8601String(),
                "end_time" => $event->end_time?->toIso8601String(),
                "description" => $event->description,
                "characters_count" => $event->characters_count ?? 0,
            ])->values()->all()
        ])->values()->all();

        $canManageSchedule = Auth::user()->can('canManageSchedule', $static);
    @endphp

    <div id="app">
        <schedule-calendar
            :static-id="{{ $static->id }}"
            static-name="{{ $static->name }}"
            :can-manage-schedule="{{ $canManageSchedule ? 'true' : 'false' }}"
            default-raid-time="{{ $static->raid_start_time ? \Carbon\Carbon::parse($static->raid_start_time)->format('H:i') : '20:00' }}"
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
