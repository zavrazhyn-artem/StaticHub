<x-app-layout>
    @php
        $canManageSchedule = Auth::user()->can('canManageSchedule', $static);
    @endphp
    <div id="app">
        <boss-planner-page
            :planner-data="{{ json_encode($plannerData) }}"
            :roster="{{ json_encode($roster) }}"
            :static-group="{{ $static->toJson() }}"
            :can-manage="{{ $canManageSchedule ? 'true' : 'false' }}"
            :my-character-ids="{{ json_encode($myCharacterIds ?? []) }}"
            csrf-token="{{ csrf_token() }}"
            :routes="{{ json_encode([
                'save' => route('statics.boss-planner.save'),
                'shareBase' => url('/boss-planner'),
                'destroyBase' => url('/boss-planner'),
            ]) }}"
        ></boss-planner-page>
    </div>
</x-app-layout>
