@php use App\Policies\StaticGroupPolicy; @endphp
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

    @php
        $dashboardData = [
            'nextRaid' => $nextRaid ? [
                'timestamp'     => $nextRaid->start_time->timestamp,
                'date'          => $nextRaid->start_time->setTimezone($static->timezone)->translatedFormat('l, M d'),
                'time'          => $nextRaid->start_time->setTimezone($static->timezone)->translatedFormat('H:i'),
                'discordPosted' => (bool) $nextRaid->discord_message_id,
            ] : null,
            'roleCounts'    => $roleCounts,
            'taxStatus'     => $taxStatus,
            'taxDescription'=> $taxDescription,
            'targetTax'     => \App\Helpers\CurrencyHelper::formatGold($targetTax, false),
            'weeklyStatus'  => $weeklyStatus ?? [],
            'paidCount'     => collect($weeklyStatus ?? [])->where('is_paid', true)->count(),
            'weekRange'     => now()->startOfWeek()->format('M d') . ' - ' . now()->endOfWeek()->format('M d'),
            'reserves'      => \App\Helpers\CurrencyHelper::formatGold($reserves),
            'autonomy'      => $autonomy,
            'weeklyCost'    => $weeklyCost,
            'raidDays'      => count($static->raid_days ?? ['wed', 'thu', 'sun']),
            'recipes'       => $recipes->map(fn($r) => [
                'icon'     => $r->display_icon_url,
                'name'     => $r->name,
                'quantity' => $r->quantity ?? $r->default_quantity,
            ])->values()->toArray(),
            'weeklySchedule'=> $weeklySchedule->map(fn($e) => [
                'id'        => $e->id,
                'month'     => $e->start_time->setTimezone($static->timezone)->format('M'),
                'day'       => $e->start_time->setTimezone($static->timezone)->format('d'),
                'dayOfWeek' => $e->start_time->setTimezone($static->timezone)->format('D'),
                'time'      => $e->start_time->setTimezone($static->timezone)->format('H:i'),
                'rsvpCount' => $e->rsvp_count,
            ])->values()->toArray(),
            'syncData'      => $syncData ?? (object)[],
            'tickInterval'  => config('sync.widget_tick_ms', 1000),
            'routes' => [
                'settings'  => route('statics.settings.schedule', $static->id),
                'calendar'  => route('schedule.index'),
                'treasury'  => route('statics.treasury', $static->id),
                'eventShow' => route('schedule.event.show', '__ID__'),
            ],
        ];
    @endphp

    <dashboard-view :data="{{ json_encode($dashboardData) }}"></dashboard-view>
</x-app-layout>
