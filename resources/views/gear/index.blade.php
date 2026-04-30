<x-app-layout>
    <gear-management
        static-name="{{ $static->name }}"
        :wishlist-payload='@json($wishlistPayload)'
        :gear-context='@json($gearContext)'
        :enchantable-slots='@json($enchantableSlots)'
        store-url="{{ route('statics.gear.wishlists.store') }}"
        :destroy-url-template="'{{ url('/gear/wishlists') }}/__ID__'"
        gear-list-store-url="{{ route('statics.gear.lists.store') }}"
        :gear-list-destroy-url-template="'{{ url('/gear/lists') }}/__ID__'"
        :gear-list-rename-url-template="'{{ url('/gear/lists') }}/__ID__'"
        :gear-list-set-slot-url-template="'{{ url('/gear/lists') }}/__ID__/slot'"
        :gear-list-import-simc-url-template="'{{ url('/gear/lists') }}/__ID__/simc'"
        gear-bis-import-url="{{ route('statics.gear.lists.import-bis') }}"
        list-summaries-url="{{ route('statics.gear.lists.summaries') }}"
        :active-list-url-template="'{{ url('/gear/lists') }}/__ID__/payload'"
        csrf-token="{{ csrf_token() }}"
        flash-success="{{ session('success', '') }}"
        flash-error="{{ ($errors ?? new \Illuminate\Support\MessageBag())->first() }}"
    ></gear-management>
</x-app-layout>
