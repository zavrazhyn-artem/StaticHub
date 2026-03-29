<x-app-layout>
    <div class="space-y-8">
        <!-- Event Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
            <div class="space-y-1">
                <div class="flex items-center gap-3">
                    <a href="{{ route('schedule.index') }}" class="text-on-surface-variant hover:text-primary transition-colors">
                        <span class="material-symbols-outlined">arrow_back</span>
                    </a>
                    <h1 class="text-4xl font-black text-white uppercase tracking-tighter font-headline">{{ $event->title }}</h1>
                </div>
                <div class="flex items-center gap-4 text-on-surface-variant font-headline text-[10px] font-bold uppercase tracking-widest">
                    <div class="flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">calendar_today</span>
                        {{ $event->start_time->setTimezone($event->static->timezone)->format('l, F j, Y') }}
                    </div>
                    <div class="flex items-center gap-1 text-primary">
                        <span class="material-symbols-outlined text-sm">schedule</span>
                        {{ $event->start_time->setTimezone($event->static->timezone)->format('H:i') }} - {{ $event->end_time ? $event->end_time->setTimezone($event->static->timezone)->format('H:i') : '??:??' }} ({{ $event->static->timezone }})
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-4">
                @if(Auth::user()->id === $event->static->owner_id)
                    <form action="{{ route('schedule.announce', $event) }}" method="POST">
                        @csrf
                        <button type="submit" class="flex items-center gap-2 bg-[#5865F2] hover:bg-[#4752C4] text-white px-4 py-2 rounded-sm font-headline text-[10px] font-bold uppercase tracking-widest transition-all">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M20.317 4.3698a19.7913 19.7913 0 00-4.8851-1.5152.0741.0741 0 00-.0785.0371c-.211.3753-.4447.8648-.6083 1.2495-1.8447-.2758-3.68-.2758-5.4868 0-.1636-.3933-.4058-.8742-.6177-1.2495a.077.077 0 00-.0785-.037 19.7363 19.7363 0 00-4.8852 1.515.0699.0699 0 00-.0321.0277C.5334 9.0458-.319 13.5799.0992 18.0578a.0824.0824 0 00.0312.0561c2.0528 1.5076 4.0413 2.4228 5.9929 3.0294a.0777.0777 0 00.0842-.0276c.4616-.6304.8731-1.2952 1.226-1.9942a.076.076 0 00-.0416-.1057c-.6528-.2476-1.2743-.5495-1.8722-.8923a.077.077 0 01-.0076-.1277c.1258-.0943.2517-.1923.3718-.2914a.0743.0743 0 01.0776-.0105c3.9278 1.7933 8.18 1.7933 12.0614 0a.0739.0739 0 01.0785.0095c.1202.099.246.1971.3728.2924a.077.077 0 01-.0066.1276 12.2986 12.2986 0 01-1.873.8914.0766.0766 0 00-.0407.1067c.3604.698.7719 1.3628 1.225 1.9932a.076.076 0 00.0842.0286c1.961-.6067 3.9495-1.5219 6.0023-3.0294a.077.077 0 00.0313-.0552c.5004-5.177-.8382-9.6739-3.5485-13.6604a.061.061 0 00-.0312-.0286zM8.02 15.3312c-1.1825 0-2.1569-1.0857-2.1569-2.419 0-1.3332.9555-2.4189 2.157-2.4189 1.2108 0 2.1757 1.0952 2.1568 2.419 0 1.3332-.9555 2.4189-2.1569 2.4189zm7.9748 0c-1.1825 0-2.1569-1.0857-2.1569-2.419 0-1.3332.9554-2.4189 2.1569-2.4189 1.2108 0 2.1757 1.0952 2.1568 2.419 0 1.3332-.946 2.4189-2.1568 2.4189z"/>
                            </svg>
                            {{ $event->discord_message_id ? 'Update Discord' : 'Post to Discord' }}
                        </button>
                    </form>
                @endif
            </div>

            <!-- Signup Form (User Action) -->
            <div class="w-full md:w-auto">
                <form action="{{ route('schedule.event.rsvp', $event) }}" method="POST" class="bg-surface-container-high border border-white/10 rounded-xl p-6 glassmorphism flex flex-col md:flex-row items-end gap-4 shadow-2xl">
                    @csrf
                    <div class="w-full md:w-48 space-y-1">
                        <label for="character_id" class="block font-headline text-[10px] font-bold text-primary uppercase tracking-widest">Character</label>
                        <select name="character_id" id="character_id" class="w-full bg-surface-container-highest border border-white/5 rounded-sm px-3 py-2 text-xs font-bold text-white uppercase tracking-widest focus:ring-1 focus:ring-primary outline-none appearance-none">
                            @foreach($userCharacters as $char)
                                <option value="{{ $char->id }}" {{ $selectedCharacterId == $char->id ? 'selected' : '' }}>
                                    {{ $char->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="w-full md:w-48 space-y-1">
                        <label for="status" class="block font-headline text-[10px] font-bold text-primary uppercase tracking-widest">Presence</label>
                        <select name="status" id="status" class="w-full bg-surface-container-highest border border-white/5 rounded-sm px-3 py-2 text-xs font-bold text-white uppercase tracking-widest focus:ring-1 focus:ring-primary outline-none appearance-none">
                            <option value="present" {{ optional($currentAttendance)->status === 'present' ? 'selected' : '' }}>Present</option>
                            <option value="absent" {{ optional($currentAttendance)->status === 'absent' ? 'selected' : '' }}>Absent</option>
                            <option value="tentative" {{ optional($currentAttendance)->status === 'tentative' ? 'selected' : '' }}>Tentative</option>
                            <option value="late" {{ optional($currentAttendance)->status === 'late' ? 'selected' : '' }}>Late</option>
                        </select>
                    </div>
                    <div class="w-full md:w-64 space-y-1">
                        <label for="comment" class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Comment (Optional)</label>
                        <input type="text" name="comment" id="comment" value="{{ optional($currentAttendance)->comment }}" placeholder="Reason for absence..."
                            class="w-full bg-surface-container-highest border border-white/5 rounded-sm px-3 py-2 text-xs text-white focus:ring-1 focus:ring-primary outline-none">
                    </div>
                    @if(session('success'))
                        <div class="absolute -top-10 left-0 bg-success-neon text-black text-[10px] font-bold px-4 py-1 rounded shadow-lg animate-bounce">
                            {{ session('success') }}
                        </div>
                    @endif
                    @if($errors->any())
                        <div class="absolute -top-12 left-0 bg-error-dim text-white text-[10px] font-bold px-4 py-1 rounded shadow-lg">
                            {{ $errors->first() }}
                        </div>
                    @endif
                    <button type="submit" class="w-full md:w-auto bg-primary text-on-primary px-6 py-2.5 rounded-sm font-headline text-xs font-bold uppercase tracking-[0.2em] hover:brightness-110 active:scale-95 transition-all">
                        Save RSVP
                    </button>
                </form>
            </div>
        </div>

        @if($event->description)
            <div class="bg-surface-container-lowest border-l-4 border-primary p-6 rounded-r-xl">
                <p class="text-on-surface-variant text-sm font-medium italic">{{ $event->description }}</p>
            </div>
        @endif

        <!-- Analysis Tabs -->
        <div x-data="{ tab: 'roster' }" class="space-y-6">
            <div class="flex items-center gap-4 border-b border-white/10">
                <button @click="tab = 'roster'" :class="tab === 'roster' ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant hover:text-white'" class="pb-4 px-2 border-b-2 font-headline text-[10px] font-black uppercase tracking-[0.2em] transition-all">
                    Roster Breakdown
                </button>
                @if($event->ai_analysis)
                    <button @click="tab = 'analysis'" :class="tab === 'analysis' ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant hover:text-white'" class="pb-4 px-2 border-b-2 font-headline text-[10px] font-black uppercase tracking-[0.2em] transition-all flex items-center gap-2">
                        <span class="material-symbols-outlined text-sm">psychology</span>
                        Tactical Analysis
                    </button>
                @endif
                @if($event->wcl_report_id)
                    <a href="https://www.warcraftlogs.com/reports/{{ $event->wcl_report_id }}" target="_blank" class="pb-4 px-2 border-b-2 border-transparent text-on-surface-variant hover:text-[#ff7d0a] font-headline text-[10px] font-black uppercase tracking-[0.2em] transition-all flex items-center gap-2">
                        <span class="material-symbols-outlined text-sm">analytics</span>
                        Warcraft Logs
                    </a>
                @endif
            </div>

            <div x-show="tab === 'roster'" class="space-y-8">
                <!-- Roster Breakdown (The Wowaudit Grid) -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    @php
                        $roles = [
                            'tank' => ['label' => 'Tanks', 'icon' => 'shield', 'color' => 'text-blue-400', 'bg' => 'bg-blue-400/5'],
                            'heal' => ['label' => 'Healers', 'icon' => 'medical_services', 'color' => 'text-success-neon', 'bg' => 'bg-success-neon/5'],
                            'mdps' => ['label' => 'Melee DPS', 'icon' => 'swords', 'color' => 'text-error-dim', 'bg' => 'bg-error-dim/5'],
                            'rdps' => ['label' => 'Ranged DPS', 'icon' => 'magic_button', 'color' => 'text-purple-400', 'bg' => 'bg-purple-400/5'],
                        ];
                    @endphp

                    @foreach($roles as $roleKey => $roleData)
                        <div class="bg-surface-container border border-white/5 rounded-xl overflow-hidden flex flex-col min-h-[400px]">
                            <div class="px-5 py-3 {{ $roleData['bg'] }} border-b border-white/5 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined {{ $roleData['color'] }} text-lg">{{ $roleData['icon'] }}</span>
                                    <span class="font-headline text-xs font-black uppercase tracking-widest text-white">{{ $roleData['label'] }}</span>
                                </div>
                                <span class="bg-white/10 px-2 py-0.5 rounded text-[10px] font-black text-white">{{ $mainRoster[$roleKey]->count() }}</span>
                            </div>

                            <div class="p-2 space-y-1 flex-1 overflow-y-auto">
                                @forelse($mainRoster[$roleKey] as $character)
                                    @php
                                        $status = $character->pivot->status;
                                        $isPending = $status === 'pending';
                                        $isLate = $status === 'late';
                                        $isPresent = $status === 'present';
                                    @endphp
                                    <div class="flex items-center justify-between p-2 rounded hover:bg-white/5 transition-colors group {{ $isPending ? 'opacity-40' : '' }}">
                                        <div class="flex items-center gap-3">
                                            <div class="relative">
                                                <img src="{{ $character->avatar_url }}" class="w-8 h-8 rounded object-cover border border-white/10 {{ $isPending ? 'grayscale' : '' }}">
                                                @if($isLate)
                                                    <div class="absolute -top-1 -right-1 w-3 h-3 bg-yellow-400 rounded-full border-2 border-surface-container flex items-center justify-center" title="Late">
                                                        <span class="material-symbols-outlined text-[8px] text-black font-bold">schedule</span>
                                                    </div>
                                                @endif
                                                @if($isPresent)
                                                    <div class="absolute -top-1 -right-1 w-3 h-3 bg-success-neon rounded-full border-2 border-surface-container flex items-center justify-center" title="Present">
                                                        <span class="material-symbols-outlined text-[8px] text-black font-bold">check</span>
                                                    </div>
                                                @endif
                                                @if($isPending)
                                                    <div class="absolute -top-1 -right-1 w-3 h-3 bg-white/20 rounded-full border-2 border-surface-container flex items-center justify-center" title="Pending">
                                                        <span class="material-symbols-outlined text-[8px] text-white/50 font-bold">question_mark</span>
                                                    </div>
                                                @endif
                                            </div>
                                            <div>
                                                <div class="text-xs font-bold text-{{ strtolower(str_replace(' ', '-', $character->playable_class)) }}">
                                                    {{ $character->name }}
                                                </div>
                                                <div class="text-[10px] text-on-surface-variant font-medium flex items-center gap-1">
                                                    {{ $character->active_spec ?? $character->playable_class }}
                                                    @if($isPending)
                                                        <span class="text-[8px] font-black uppercase tracking-tighter opacity-50">(Pending)</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        <div class="opacity-0 group-hover:opacity-100 transition-opacity">
                                            @if($character->pivot->comment)
                                                <span class="material-symbols-outlined text-on-surface-variant text-sm cursor-help" title="{{ $character->pivot->comment }}">chat_bubble</span>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <div class="flex flex-col items-center justify-center py-12 text-center opacity-20">
                                        <span class="material-symbols-outlined text-4xl mb-2">{{ $roleData['icon'] }}</span>
                                        <span class="text-[10px] uppercase font-bold tracking-widest">Empty</span>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            @if($event->ai_analysis)
                <div x-show="tab === 'analysis'" class="space-y-6" x-cloak>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="lg:col-span-2 space-y-6">
                            <!-- Overall Strategy -->
                            <div class="bg-surface-container-high border border-white/5 rounded-2xl p-6">
                                <h3 class="text-primary font-headline text-xs font-black uppercase tracking-widest mb-4 flex items-center gap-2">
                                    <span class="material-symbols-outlined">strategy</span>
                                    Overall Strategy & Execution
                                </h3>
                                <div class="prose prose-invert prose-sm max-w-none text-on-surface-variant">
                                    {!! Str::markdown($event->ai_analysis['strategy'] ?? $event->ai_analysis['Overall Strategy & Execution'] ?? 'No strategy data available.') !!}
                                </div>
                            </div>

                            <!-- Wipe Reasons -->
                            <div class="bg-surface-container-high border border-white/5 rounded-2xl p-6">
                                <h3 class="text-error font-headline text-xs font-black uppercase tracking-widest mb-4 flex items-center gap-2">
                                    <span class="material-symbols-outlined">dangerous</span>
                                    Critical Failures & Wipe Reasons
                                </h3>
                                <div class="prose prose-invert prose-sm max-w-none text-on-surface-variant">
                                    {!! Str::markdown($event->ai_analysis['wipes'] ?? $event->ai_analysis['Major Wipe Reasons'] ?? 'No wipe data available.') !!}
                                </div>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <!-- Individual Highlights -->
                            <div class="bg-surface-container-high border border-white/5 rounded-2xl p-6">
                                <h3 class="text-success-neon font-headline text-xs font-black uppercase tracking-widest mb-4 flex items-center gap-2">
                                    <span class="material-symbols-outlined">person</span>
                                    Performance Highlights
                                </h3>
                                <div class="prose prose-invert prose-sm max-w-none text-on-surface-variant">
                                    {!! Str::markdown($event->ai_analysis['individual'] ?? $event->ai_analysis['Individual Highlights/Issues'] ?? 'No individual data available.') !!}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Declined / Tentative Section -->
        @if($absentRoster->count() > 0)
            <section class="space-y-4 pt-4">
                <h3 class="font-headline text-xs font-bold text-on-surface-variant uppercase tracking-[0.2em] flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">person_off</span>
                    Absent / Tentative
                </h3>
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                    @foreach($absentRoster as $character)
                        <div class="bg-surface-container-low border border-white/5 p-3 rounded-lg flex items-center gap-3 opacity-60 hover:opacity-100 transition-opacity relative group">
                            <img src="{{ $character->avatar_url }}" class="w-8 h-8 rounded object-cover grayscale group-hover:grayscale-0 transition-all">
                            <div>
                                <div class="text-[10px] font-bold text-white leading-tight">{{ $character->name }}</div>
                                <div class="text-[8px] font-black uppercase tracking-widest {{ in_array($character->pivot->status, ['absent', 'pending']) ? 'text-error' : ($character->pivot->status === 'tentative' ? 'text-yellow-400' : 'text-primary') }}">
                                    {{ $character->pivot->status }}
                                </div>
                            </div>
                            @if($character->pivot->comment)
                                <div class="absolute top-1 right-1">
                                    <span class="material-symbols-outlined text-[10px] text-on-surface-variant cursor-help" title="{{ $character->pivot->comment }}">info</span>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    <style>
        .text-death-knight { color: #C41F3B; }
        .text-demon-hunter { color: #A330C9; }
        .text-druid { color: #FF7C0A; }
        .text-evoker { color: #33937F; }
        .text-hunter { color: #ABD473; }
        .text-mage { color: #3FC7EB; }
        .text-monk { color: #00FF98; }
        .text-paladin { color: #F48CBA; }
        .text-priest { color: #FFFFFF; }
        .text-rogue { color: #FFF468; }
        .text-shaman { color: #0070DD; }
        .text-warlock { color: #8788EE; }
        .text-warrior { color: #C69B6D; }
    </style>
</x-app-layout>
