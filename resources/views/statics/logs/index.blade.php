<x-app-layout>
    <logs-index
        static-name="{{ $static->name }}"
        :logs='@json($logsData)'
        filter-url="{{ route('statics.logs.index') }}"
        current-difficulties="{{ $currentDifficulties ?? '' }}"
        current-from-date="{{ $currentFromDate ?? '' }}"
        current-to-date="{{ $currentToDate ?? '' }}"
        manual-log-url="{{ route('statics.logs.manual.store') }}"
        :manual-log-enabled="{{ app()->environment(['local', 'development']) ? 'true' : 'false' }}"
        :cooldown-state='@json($cooldownState)'
    ></logs-index>

    <div class="max-w-9/10 mx-auto px-4 sm:px-6 lg:px-8 mt-12">
        {{ $logs->links() }}
    </div>
</x-app-layout>
