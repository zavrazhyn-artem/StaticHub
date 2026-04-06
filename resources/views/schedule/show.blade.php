<x-app-layout>
    @php
        $canManageSchedule    = Auth::user()->can('canManageSchedule', $event->static);
        $canAnnounceToDiscord = Auth::user()->can('canAnnounceToDiscord', $event->static);
    @endphp
    <div id="app">
        <raid-event-details
            :event="{{ $event->toJson() }}"
            :user-characters="{{ $userCharacters->toJson() }}"
            :selected-character-id="{{ $selectedCharacterId ?? 'null' }}"
            :current-attendance="{{ $currentAttendance ? $currentAttendance->toJson() : 'null' }}"
            :main-roster="{{ json_encode($mainRoster) }}"
            :absent-roster="{{ $absentRoster->toJson() }}"
            :character-specs="{{ json_encode($characterSpecs ?? []) }}"
            :auth-user-id="{{ Auth::user()->id }}"
            :can-manage-schedule="{{ $canManageSchedule ? 'true' : 'false' }}"
            :can-announce-to-discord="{{ $canAnnounceToDiscord ? 'true' : 'false' }}"
            csrf-token="{{ csrf_token() }}"
            :routes="{{ json_encode([
                'index'    => route('schedule.index'),
                'rsvp'     => route('schedule.event.rsvp', $event),
                'announce' => route('schedule.announce', $event),
                'update'   => route('schedule.event.update', $event),
                'destroy'  => route('schedule.event.destroy', $event),
            ]) }}"
            success-message="{{ session('success') }}"
            :errors="{{ $errors->any() ? json_encode($errors->toArray()) : '{}' }}"
        ></raid-event-details>
    </div>
</x-app-layout>
