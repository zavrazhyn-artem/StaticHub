@php
    $tabUrls = [
        'gear'         => route('statics.gear'),
        'wishlist'     => route('statics.gear.tab', ['tab' => 'wishlist']),
        'loot-history' => route('statics.gear.tab', ['tab' => 'loot-history']),
    ];
@endphp
<x-app-layout>
    <gear-management
        static-name="{{ $static->name }}"
        :wishlist-payload='@json($wishlistPayload)'
        :wishlist-configs='@json($wishlistConfigs)'
        :gear-context='@json($gearContext)'
        :enchantable-slots='@json($enchantableSlots)'
        store-url="{{ route('statics.gear.wishlists.store') }}"
        :destroy-url-template="'{{ url('/gear/wishlists') }}/__ID__'"
        gear-list-store-url="{{ route('statics.gear.lists.store') }}"
        :gear-list-destroy-url-template="'{{ url('/gear/lists') }}/__ID__'"
        :gear-list-rename-url-template="'{{ url('/gear/lists') }}/__ID__'"
        :gear-list-set-slot-url-template="'{{ url('/gear/lists') }}/__ID__/slot'"
        :gear-list-picker-url-template="'{{ url('/gear/lists') }}/__ID__/picker'"
        :gear-list-export-simc-url-template="'{{ url('/gear/lists') }}/__ID__/export-simc'"
        :gear-list-import-simc-url-template="'{{ url('/gear/lists') }}/__ID__/simc'"
        gear-bis-import-url="{{ route('statics.gear.lists.import-bis') }}"
        list-summaries-url="{{ route('statics.gear.lists.summaries') }}"
        :active-list-url-template="'{{ url('/gear/lists') }}/__ID__/payload'"
        loot-history-payload-url="{{ route('statics.gear.loot-history.payload') }}"
        initial-tab="{{ $initialTab }}"
        :tab-urls='@json($tabUrls)'
        csrf-token="{{ csrf_token() }}"
        flash-success="{{ session('success', '') }}"
        flash-error="{{ ($errors ?? new \Illuminate\Support\MessageBag())->first() }}"
    ></gear-management>
</x-app-layout>
