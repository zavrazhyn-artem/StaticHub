@php
    $canManageTreasury = Auth::user()->can('canManageTreasury', $static);
@endphp
<x-app-layout>
    <transaction-history
        :static-id="{{ $static->id }}"
        static-name="{{ $static->name }}"
        :can-manage-treasury="{{ $canManageTreasury ? 'true' : 'false' }}"
        :transactions='@json($transactions->items())'
        :members='@json($members)'
        selected-user-id="{{ $selectedUserId ?? '' }}"
        filter-url="{{ route('statics.treasury.history', $static) }}"
        treasury-url="{{ route('statics.treasury', $static) }}"
        csrf-token="{{ csrf_token() }}"
    ></transaction-history>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-12">
        {{ $transactions->withQueryString()->links() }}
    </div>
</x-app-layout>
