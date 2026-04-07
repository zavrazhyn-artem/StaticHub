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
        </header>
    </section>

    <dashboard-view :data="{{ json_encode($dashboardData) }}"></dashboard-view>
</x-app-layout>
