<x-app-layout>
    <section class="mb-8">
        <header class="flex items-end justify-between border-b border-white/5 pb-4">
            <div>
                <span
                    class="text-on-surface-variant font-headline text-xs font-bold uppercase tracking-widest">{{ __('War Room Dashboard') }}</span>
                <h1 class="font-headline text-4xl font-extrabold tracking-tight flex items-center gap-3">
                    <span class="material-symbols-outlined text-success-neon">radio_button_checked</span>
                    {{ __('TACTICAL STATUS:') }} {{ $static->name }}
                </h1>
            </div>
            <div class="flex gap-2">
                @can('manage', $static)
                    <a href="{{ route('statics.settings.schedule', $static->id) }}"
                       class="bg-surface-container-high hover:bg-surface-container-highest text-white px-4 py-2 rounded-lg text-sm font-bold transition-all flex items-center gap-2">
                        <span class="material-symbols-outlined text-sm">settings</span>
                        {{ __('SETTINGS') }}
                    </a>
                @endcan
                <a href="{{ route('schedule.index') }}"
                   class="bg-surface-container-high hover:bg-surface-container-highest text-white px-4 py-2 rounded-lg text-sm font-bold transition-all flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">calendar_month</span>
                    {{ __('OPEN CALENDAR') }}
                </a>
            </div>
        </header>
    </section>

    <dashboard-view :data="{{ json_encode($dashboardData) }}"></dashboard-view>
</x-app-layout>
