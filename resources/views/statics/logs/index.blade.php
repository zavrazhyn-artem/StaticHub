<x-app-layout>
    <logs-index
        static-name="{{ $static->name }}"
        :logs='@json($logsData)'
        filter-url="{{ route('statics.logs.index', $static) }}"
        current-difficulties="{{ $currentDifficulties ?? '' }}"
        current-from-date="{{ $currentFromDate ?? '' }}"
        current-to-date="{{ $currentToDate ?? '' }}"
        manual-log-url="{{ route('statics.logs.manual.store', $static) }}"
    ></logs-index>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-12">
        {{ $logs->links() }}
    </div>
</x-app-layout>
