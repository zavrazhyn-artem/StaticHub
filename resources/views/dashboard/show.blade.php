<x-app-layout>
    <section class="mb-8">
        <header class="flex items-end justify-between border-b border-white/5 pb-4">
            <div>
                <span class="text-on-surface-variant font-headline text-xs font-bold uppercase tracking-widest">
                    {{ __('War Room Dashboard') }}
                    <span class="text-success-neon/60"> · </span>
                    {{ __('Tactical Status') }}
                </span>
                <h1 class="font-headline text-4xl font-black tracking-tight flex items-center gap-3 mt-1">
                    <span class="material-symbols-outlined text-success-neon">radio_button_checked</span>
                    {{ $static->name }}
                </h1>
            </div>
        </header>
    </section>

    <dashboard-view :data="{{ json_encode($dashboardData) }}"></dashboard-view>
</x-app-layout>
