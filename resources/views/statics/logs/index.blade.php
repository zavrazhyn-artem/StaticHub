<x-app-layout>
    @php
        $logsData = array_map(fn($log) => [
            'id'           => $log->id,
            'title'        => $log->title,
            'difficulties' => $log->difficulties ?? [],
            'date'         => $log->created_at->format('M d, Y'),
            'has_ai'       => (bool) $log->ai_analysis,
            'url'          => route('statics.logs.show', [$static, $log]),
        ], $logs->items());
    @endphp

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
