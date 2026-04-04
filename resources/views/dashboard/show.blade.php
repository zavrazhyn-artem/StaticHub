<x-app-layout>
    <section class="mb-8">
        <header class="flex items-end justify-between border-b border-white/5 pb-4">
            <div>
                <span class="text-on-surface-variant font-headline text-xs font-bold uppercase tracking-widest">War Room Dashboard</span>
                <h1 class="font-headline text-4xl font-extrabold tracking-tight flex items-center gap-3">
                    <span class="material-symbols-outlined text-success-neon">radio_button_checked</span>
                    TACTICAL STATUS: {{ $static->name }}
                </h1>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('statics.settings.schedule', $static->id) }}" class="bg-surface-container-high hover:bg-surface-container-highest text-white px-4 py-2 rounded-lg text-sm font-bold transition-all flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">settings</span>
                    SETTINGS
                </a>
                <a href="{{ route('schedule.index') }}" class="bg-surface-container-high hover:bg-surface-container-highest text-white px-4 py-2 rounded-lg text-sm font-bold transition-all flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">calendar_month</span>
                    OPEN CALENDAR
                </a>
            </div>
        </header>
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <div class="lg:col-span-2 space-y-6">

            <div class="bg-surface-container-high rounded-2xl border border-white/5 p-6 relative overflow-hidden group">
                <div class="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-full -mr-16 -mt-16 blur-3xl group-hover:bg-primary/10 transition-colors"></div>

                <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 relative z-10">
                    <div>
                        <h2 class="text-on-surface-variant font-headline text-xs font-bold uppercase tracking-widest mb-1">Incoming Objective</h2>
                        @if($nextRaid)
                            <h3 class="text-3xl font-extrabold text-white tracking-tight">Raid Event</h3>
                            <p class="text-primary font-bold mt-1">
                                {{ $nextRaid->start_time->setTimezone($static->timezone)->format('l, M d') }} @ {{ $nextRaid->start_time->setTimezone($static->timezone)->format('H:i') }}
                            </p>
                        @else
                            <h3 class="text-3xl font-extrabold text-on-surface-variant tracking-tight italic">No scheduled raids</h3>
                        @endif
                    </div>

                    @if($nextRaid)
                        <div class="flex items-center gap-6">
                            <div class="text-center">
                                <div class="text-3xl font-black text-white" x-data="{
                                target: {{ $nextRaid->start_time->timestamp }},
                                days: 0, hours: 0, mins: 0,
                                init() {
                                    setInterval(() => {
                                        let diff = this.target - Math.floor(Date.now() / 1000);
                                        if (diff < 0) diff = 0;
                                        this.days = Math.floor(diff / 86400);
                                        this.hours = Math.floor((diff % 86400) / 3600);
                                        this.mins = Math.floor((diff % 3600) / 60);
                                    }, 1000);
                                }
                            }">
                                    <span x-text="days">0</span>d <span x-text="hours">0</span>h <span x-text="mins">0</span>m
                                </div>
                                <div class="text-[10px] text-on-surface-variant font-bold uppercase tracking-widest">Countdown to Pull</div>
                            </div>
                            <div class="h-12 w-px bg-white/10 hidden md:block"></div>
                            <div>
                                @if($nextRaid->discord_message_id)
                                    <div class="flex items-center gap-2 text-success-neon">
                                        <span class="material-symbols-outlined text-sm">check_circle</span>
                                        <span class="text-xs font-bold uppercase tracking-wider">Discord Posted</span>
                                    </div>
                                @else
                                    <div class="flex items-center gap-2 text-on-surface-variant">
                                        <span class="material-symbols-outlined text-sm">schedule</span>
                                        <span class="text-xs font-bold uppercase tracking-wider">Pending Post</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-surface-container-high rounded-2xl border border-white/5 p-6">
                    <h2 class="text-on-surface-variant font-headline text-xs font-bold uppercase tracking-widest mb-6">Roster Readiness</h2>
                    <div class="space-y-4">
                        @php
                            $roles = [
                                'tank' => ['label' => 'Tanks', 'color' => 'bg-blue-500', 'max' => 2],
                                'heal' => ['label' => 'Healers', 'color' => 'bg-success-neon', 'max' => 4],
                                'mdps' => ['label' => 'Melee', 'color' => 'bg-error-dim', 'max' => 6],
                                'rdps' => ['label' => 'Ranged', 'color' => 'bg-purple-500', 'max' => 8],
                            ];
                        @endphp
                        @foreach($roles as $key => $meta)
                            @php
                                $count = $roleCounts[$key] ?? 0;
                                $percent = min(100, ($count / $meta['max']) * 100);
                            @endphp
                            <div class="space-y-1">
                                <div class="flex justify-between text-xs font-bold uppercase tracking-wider">
                                    <span class="text-white">{{ $meta['label'] }}</span>
                                    <span class="text-on-surface-variant">{{ $count }} / {{ $meta['max'] }}</span>
                                </div>
                                <div class="h-2 bg-black/40 rounded-full overflow-hidden">
                                    <div class="h-full {{ $meta['color'] }} shadow-[0_0_10px_rgba(0,0,0,0.5)] transition-all duration-1000" style="width: {{ $percent }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="bg-surface-container-high rounded-2xl border border-white/5 p-6 relative overflow-hidden group {{ $taxStatus === 'danger' ? 'border-l-4 border-error' : '' }}">
                    <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
                        <span class="material-symbols-outlined text-6xl text-[#FFD700]">payments</span>
                    </div>
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-on-surface-variant font-headline text-xs font-bold uppercase tracking-widest">Treasury Overview</h2>
                        @if($taxStatus !== 'success')
                            <span class="material-symbols-outlined {{ $taxStatus === 'danger' ? 'text-error animate-pulse' : 'text-warning' }} text-lg" title="{{ $taxDescription }}">
                                {{ $taxStatus === 'danger' ? 'warning' : 'info' }}
                            </span>
                        @endif
                    </div>

                    <div class="space-y-6">
                        <div>
                            <div class="text-[10px] {{ $taxStatus === 'danger' ? 'text-error' : ($taxStatus === 'warning' ? 'text-warning' : 'text-on-surface-variant') }} font-bold uppercase tracking-widest mb-1 flex items-center gap-1">
                                @if($taxStatus !== 'success')
                                    <span class="material-symbols-outlined text-[12px]">{{ $taxStatus === 'danger' ? 'trending_up' : 'trending_down' }}</span>
                                @endif
                                Weekly Tax Goal
                            </div>
                            <div class="flex items-baseline gap-2">
                                <span class="text-3xl font-black {{ $taxStatus === 'danger' ? 'text-error' : ($taxStatus === 'warning' ? 'text-warning' : 'text-white') }} font-headline tracking-tighter">{{ \App\Helpers\CurrencyHelper::formatGold($targetTax, false) }}</span>
                                <span class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Gold / Player</span>
                            </div>
                            @if($taxStatus !== 'success')
                                <div class="mt-1 text-[8px] {{ $taxStatus === 'danger' ? 'text-error' : 'text-warning' }} font-bold uppercase tracking-tighter">
                                    {{ $taxDescription }}
                                </div>
                            @endif
                        </div>

                        <div class="pt-4 border-t border-white/5">
                            <div class="text-[10px] text-on-surface-variant font-bold uppercase tracking-widest mb-3 text-center">Collection Progress ({{ now()->startOfWeek()->format('M d') }} - {{ now()->endOfWeek()->format('M d') }})</div>
                            <div class="grid grid-cols-10 gap-1">
                                @foreach($weeklyStatus ?? [] as $status)
                                    <div class="h-1.5 rounded-full {{ $status['is_paid'] ? 'bg-success-neon shadow-[0_0_5px_rgba(0,255,153,0.3)]' : 'bg-white/10' }}"
                                         title="{{ $status['name'] }}: {{ $status['is_paid'] ? 'Paid' : 'Pending' }}"></div>
                                @endforeach
                                @for($i = count($weeklyStatus ?? []); $i < 20; $i++)
                                    <div class="h-1.5 rounded-full bg-white/5 opacity-50 border border-dashed border-white/10" title="Empty Slot"></div>
                                @endfor
                            </div>
                            <div class="flex justify-between mt-2">
                                <span class="text-[9px] font-bold text-on-surface-variant uppercase tracking-widest">
                                    Paid: {{ collect($weeklyStatus ?? [])->where('is_paid', true)->count() }} / 20
                                </span>
                                <a href="{{ route('statics.treasury', $static->id) }}" class="text-[9px] font-bold text-primary hover:text-white uppercase tracking-widest flex items-center gap-1">
                                    Full Ledger
                                    <span class="material-symbols-outlined text-[10px]">chevron_right</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-surface-container-high rounded-2xl border border-white/5 p-6">
                <h2 class="text-on-surface-variant font-headline text-xs font-bold uppercase tracking-widest mb-6">Logistics Snapshot</h2>
                <div class="space-y-4">
                    <a href="{{ route('statics.treasury', $static->id) }}" class="flex items-center justify-between p-3 bg-black/20 rounded-xl border border-white/5 hover:bg-white/5 transition-colors group">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-tertiary-dim/10 flex items-center justify-center">
                                <span class="material-symbols-outlined text-tertiary-dim">savings</span>
                            </div>
                            <div>
                                <div class="text-[10px] text-on-surface-variant font-bold uppercase tracking-widest">Guild Bank</div>
                                <div class="text-sm font-black text-[#FFD700]">{{ \App\Helpers\CurrencyHelper::formatGold($reserves) }}</div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-[8px] text-on-surface-variant font-bold uppercase tracking-[0.2em]">Autonomy</div>
                            <div class="text-xs font-black {{ $autonomy > 2 ? 'text-success-neon' : 'text-error' }}">{{ $autonomy }} <span class="text-[8px] font-normal opacity-60">WEEKS</span></div>
                            @if($weeklyCost == 0)
                                <div class="text-[8px] text-error font-bold uppercase tracking-tighter">PLANNING NEEDED</div>
                            @endif
                        </div>
                    </a>

                    <div class="grid grid-cols-2 gap-3">
                        @php
                            $raidDays = count($static->raid_days ?? ['wed', 'thu', 'sun']);
                        @endphp
                        @foreach($recipes as $recipe)
                            <div class="p-3 bg-black/20 rounded-xl border border-white/5 flex items-center gap-3">
                                <img src="{{ $recipe->display_icon_url }}" class="w-8 h-8 rounded border border-white/10" alt="{{ $recipe->name }}">
                                <div class="min-w-0">
                                    <div class="text-[9px] text-on-surface-variant font-bold uppercase tracking-widest truncate">{{ $recipe->name }}</div>
                                    <div class="text-lg font-black text-white mt-0.5">
                                        {{ $recipe->default_quantity * $raidDays }}
                                        <span class="text-[10px] font-normal text-on-surface-variant uppercase ml-1">Needed / Week</span>
                                    </div>
                                </div>
                            </div>
                            @if($loop->iteration >= 2) @break @endif
                        @endforeach
                    </div>
                </div>
            </div>

        </div> <div class="flex flex-col gap-6 h-full">

            <div class="bg-surface-container-high rounded-2xl border border-white/5 p-6 items-center justify-center">
                <sync-status-widget :sync-data="{{ json_encode($syncData ?? (object)[]) }}"></sync-status-widget>
            </div>

            <div class="bg-surface-container-high rounded-2xl border border-white/5 p-6 flex flex-col gap-8 flex-1">

                <div>
                    <h2 class="text-on-surface-variant font-headline text-xs font-bold uppercase tracking-widest mb-6">Upcoming Objectives</h2>
                    <div class="space-y-4 max-h-[360px] overflow-y-auto pr-2 custom-scrollbar">
                        @forelse($weeklySchedule as $event)
                            <div class="flex items-center justify-between p-4 bg-black/10 rounded-xl border border-white/5 hover:bg-white/5 transition-colors group">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-lg bg-surface-container-highest flex flex-col items-center justify-center border border-white/5 group-hover:border-primary/50 transition-colors">
                                        <span class="text-[10px] font-bold text-on-surface-variant uppercase tracking-tighter leading-none">{{ $event->start_time->setTimezone($static->timezone)->format('M') }}</span>
                                        <span class="text-xl font-black text-white leading-none">{{ $event->start_time->setTimezone($static->timezone)->format('d') }}</span>
                                        <span class="text-[10px] font-bold text-primary uppercase tracking-tighter leading-none mt-0.5">{{ $event->start_time->setTimezone($static->timezone)->format('D') }}</span>
                                    </div>
                                    <div>
                                        <div class="text-xs font-bold text-white uppercase tracking-wider">Raid Event</div>
                                        <div class="text-xs text-on-surface-variant flex items-center gap-2">
                                            <span class="material-symbols-outlined text-[12px]">schedule</span>
                                            {{ $event->start_time->setTimezone($static->timezone)->format('H:i') }}
                                            <span class="mx-1 opacity-20">•</span>
                                            <span class="flex items-center gap-1">
                                                <span class="material-symbols-outlined text-[12px] text-success-neon">groups</span>
                                                {{ $event->rsvp_count }} RSVPs
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <a href="{{ route('schedule.event.show', $event->id) }}" class="bg-surface-container-highest hover:bg-primary/20 hover:text-primary text-on-surface-variant px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all">
                                    MISSION INTEL
                                </a>
                            </div>
                        @empty
                            <div class="text-center py-8 text-on-surface-variant italic border border-dashed border-white/5 rounded-xl">
                                No additional objectives detected for this cycle.
                            </div>
                        @endforelse
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>
