<x-app-layout>
    <div class="space-y-6" x-data="{ showModal: false, selectedDate: '', selectedDateTime: '20:00' }">
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <h1 class="text-4xl font-black text-white uppercase tracking-tighter font-headline">Raid Schedule</h1>
                <p class="text-on-surface-variant font-medium mt-1 uppercase tracking-widest text-xs">{{ $static->name }} • {{ $current_date->format('F Y') }}</p>
            </div>

            <div class="flex items-center gap-2">
                @if($static->owner_id === auth()->id())
                    <a href="{{ route('statics.settings.schedule', $static) }}"
                       class="bg-primary/10 text-primary hover:bg-primary/20 px-4 py-2 rounded-sm font-headline text-[10px] font-bold uppercase tracking-widest transition-colors flex items-center gap-2 mr-4">
                        <span class="material-symbols-outlined text-sm">settings</span>
                        Static Settings
                    </a>
                @endif

                <a href="{{ route('schedule.index', ['year' => $prev_month->year, 'month' => $prev_month->month]) }}"
                   class="bg-surface-container-high text-on-surface-variant hover:text-white px-4 py-2 rounded-sm font-headline text-[10px] font-bold uppercase tracking-widest transition-colors flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">chevron_left</span>
                    Previous
                </a>
                <a href="{{ route('schedule.index') }}"
                   class="bg-surface-container-high text-on-surface-variant hover:text-white px-4 py-2 rounded-sm font-headline text-[10px] font-bold uppercase tracking-widest transition-colors">
                    Today
                </a>
                <a href="{{ route('schedule.index', ['year' => $next_month->year, 'month' => $next_month->month]) }}"
                   class="bg-surface-container-high text-on-surface-variant hover:text-white px-4 py-2 rounded-sm font-headline text-[10px] font-bold uppercase tracking-widest transition-colors flex items-center gap-2">
                    Next
                    <span class="material-symbols-outlined text-sm">chevron_right</span>
                </a>
            </div>
        </div>

        <!-- Calendar Container -->
        <div class="bg-white/5 border border-white/5 rounded-xl overflow-hidden shadow-2xl backdrop-blur-sm">
            <!-- Days of Week Header -->
            <div class="grid grid-cols-7 border-b border-white/5 bg-surface-container">
                @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dayName)
                    <div class="py-3 text-center">
                        <span class="text-[10px] uppercase tracking-widest text-on-surface-variant font-bold font-headline">{{ $dayName }}</span>
                    </div>
                @endforeach
            </div>

            <!-- Calendar Grid -->
            <div class="grid grid-cols-7 gap-px bg-white/5">
                @foreach($grid as $day)
                    <div @click="selectedDate = '{{ $day['date']->format('Y-m-d') }}'; selectedDateTime = '{{ $static->raid_start_time ? \Carbon\Carbon::parse($static->raid_start_time)->format('H:i') : '20:00' }}'; showModal = true"
                         class="min-h-[100px] bg-surface-container-lowest p-3 transition-colors hover:bg-surface-container-low relative group cursor-pointer {{ !$day['is_current_month'] ? 'opacity-40' : '' }} {{ $day['is_today'] ? 'bg-primary/5' : '' }}">
                        <!-- Date Number -->
                        <div class="flex justify-between items-start mb-2">
                            <span class="font-headline font-bold text-sm {{ $day['is_today'] ? 'text-primary' : 'text-on-surface-variant' }}">
                                {{ $day['date']->format('j') }}
                            </span>
                        </div>

                        <!-- Events List -->
                        <div class="space-y-1">
                            @foreach($day['events'] as $event)
                                <a href="{{ route('schedule.event.show', $event) }}" class="block bg-primary/20 border border-primary/30 text-primary text-[10px] px-2 py-1 rounded-sm mb-1 truncate hover:bg-primary/30 transition-colors">
                                    <span class="font-bold">{{ $event->start_time->format('H:i') }}</span> {{ $event->title }}
                                </a>
                            @endforeach
                        </div>

                        <!-- Add Event Button (Visible on hover) -->
                        <div class="absolute bottom-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <span class="material-symbols-outlined text-on-surface-variant hover:text-primary text-lg">add_circle</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Manual Event Modal -->
        <div x-show="showModal"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
             style="display: none;">

            <div @click.away="showModal = false" class="w-full max-w-md bg-surface-container border border-white/10 rounded-xl shadow-2xl overflow-hidden glassmorphism">
                <div class="px-6 py-4 border-b border-white/5 flex justify-between items-center">
                    <h3 class="font-headline text-xs font-bold text-white uppercase tracking-widest">Create Manual Event</h3>
                    <button @click="showModal = false" class="text-on-surface-variant hover:text-white transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <form action="{{ route('schedule.store') }}" method="POST" class="p-6 space-y-4">
                    @csrf
                    <input type="hidden" name="static_id" value="{{ $static->id }}">
                    <input type="hidden" name="date" :value="selectedDate">

                    <div class="space-y-1">
                        <label class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Date Selected</label>
                        <div class="text-white font-headline text-sm font-bold uppercase tracking-tight" x-text="selectedDate"></div>
                    </div>

                    <div class="space-y-1">
                        <label for="title" class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Event Title</label>
                        <input type="text" name="title" id="title" required placeholder="e.g., Farm Raid, Trial Runs"
                            class="w-full bg-surface-container-highest border border-white/5 rounded-sm px-3 py-2 text-sm text-white focus:ring-1 focus:ring-primary focus:border-transparent outline-none">
                    </div>

                    <div class="space-y-1">
                        <label for="time" class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Start Time</label>
                        <input type="time" name="time" id="time" required x-model="selectedDateTime"
                            class="w-full bg-surface-container-highest border border-white/5 rounded-sm px-3 py-2 text-sm text-white focus:ring-1 focus:ring-primary focus:border-transparent outline-none">
                    </div>

                    <div class="space-y-1">
                        <label for="description" class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Description (Optional)</label>
                        <textarea name="description" id="description" rows="3"
                            class="w-full bg-surface-container-highest border border-white/5 rounded-sm px-3 py-2 text-sm text-white focus:ring-1 focus:ring-primary focus:border-transparent outline-none"></textarea>
                    </div>

                    <div class="pt-4">
                        <button type="submit" class="w-full bg-primary text-on-primary py-3 rounded-sm font-headline text-xs font-bold uppercase tracking-[0.2em] hover:brightness-110 active:scale-95 transition-all">
                            Create Event
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
