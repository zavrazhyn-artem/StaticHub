<x-app-layout>
    <div class="mb-12">
        <div class="flex justify-between items-end mb-4">
            <div>
                <span class="text-cyan-400 font-headline text-xs font-bold uppercase tracking-[0.3em] mb-2 block">{{ __('— Strategic Command') }}</span>
                <h2 class="font-headline text-4xl font-black text-white uppercase tracking-tighter italic">
                    {{ __('Static Participation') }}
                </h2>
                <p class="text-on-surface-variant font-medium mt-2 max-w-2xl text-sm leading-relaxed">
                    {{ __('Manage your active roster for the current Mythic tier. Designate your main and alts to assist the Guild Master in raid composition planning.') }}
                </p>
            </div>
            <form action="{{ route('characters.import') }}" method="POST">
                @csrf
                <button type="submit" class="flex items-center gap-2 bg-surface-container-highest border border-white/5 text-white px-6 py-3 font-headline font-bold text-xs uppercase tracking-widest rounded hover:bg-surface-bright active:scale-95 transition-all">
                    <span class="material-symbols-outlined text-sm">sync</span>
                    {{ __('Sync Battle.net') }}
                </button>
            </form>
        </div>
    </div>

    <div class="space-y-12">
        @if(session('success'))
            <div class="bg-success-neon/10 border border-success-neon/20 p-4 rounded-lg flex items-center gap-3">
                <span class="material-symbols-outlined text-success-neon">check_circle</span>
                <span class="text-success-neon text-xs font-bold uppercase tracking-widest">{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="bg-error-dim/10 border border-error-dim/20 p-4 rounded-lg flex items-center gap-3">
                <span class="material-symbols-outlined text-error-dim">error</span>
                <span class="text-error-dim text-xs font-bold uppercase tracking-widest">{{ session('error') }}</span>
            </div>
        @endif

        @if($characters->isEmpty())
            <div class="bg-surface-container-low p-12 rounded-xl border border-white/5 text-center">
                <span class="material-symbols-outlined text-6xl text-on-surface-variant mb-4">group_off</span>
                <p class="text-on-surface-variant font-headline text-sm font-bold uppercase tracking-widest">{{ __('No characters found. Click the sync button to import them.') }}</p>
            </div>
        @elseif(!$static)
            <div class="bg-surface-container-low p-12 rounded-xl border border-white/5 text-center">
                <span class="material-symbols-outlined text-6xl text-on-surface-variant mb-4">error</span>
                <p class="text-on-surface-variant font-headline text-sm font-bold uppercase tracking-widest">{{ __('You must belong to at least one static to manage roster participation.') }}</p>
            </div>
        @else
            <form action="{{ route('roster.updateParticipation', $static->id) }}" method="POST"
                x-data="{ submit() { $el.submit() } }"
                x-on:change="submit()">
                @csrf

                <div class="bg-surface-container-high rounded-xl border border-white/5 overflow-hidden">
                    @if(session('warning'))
                        <div class="bg-warning/10 border-b border-white/5 p-4 flex items-center gap-3">
                            <span class="material-symbols-outlined text-warning">info</span>
                            <span class="text-warning text-xs font-bold uppercase tracking-widest">{{ session('warning') }}</span>
                            <input type="hidden" name="onboarding" value="1">
                        </div>
                    @endif
                    <!-- Table Header -->
                    <div class="bg-black/40 px-8 py-5 border-b border-white/5 grid grid-cols-12 items-center">
                        <div class="col-span-4 font-headline text-[10px] font-bold uppercase tracking-[0.2em] text-on-surface-variant">{{ __('Character') }}</div>
                        <div class="col-span-2 font-headline text-[10px] font-bold uppercase tracking-[0.2em] text-on-surface-variant text-center">{{ __('Item Level') }}</div>
                        <div class="col-span-2 font-headline text-[10px] font-bold uppercase tracking-[0.2em] text-on-surface-variant">{{ __('Realm') }}</div>
                        <div class="col-span-1 font-headline text-[10px] font-bold uppercase tracking-[0.2em] text-on-surface-variant text-center">{{ __('Main') }}</div>
                        <div class="col-span-1 font-headline text-[10px] font-bold uppercase tracking-[0.2em] text-on-surface-variant text-center">{{ __('Alt') }}</div>
                        <div class="col-span-2 font-headline text-[10px] font-bold uppercase tracking-[0.2em] text-on-surface-variant">{{ __('Role') }}</div>
                    </div>

                    <!-- Table Body -->
                    <div class="divide-y divide-white/5">
                        @foreach($characters as $character)
                            @php
                                $classColor = strtolower(str_replace([' ', "'"], '-', $character->playable_class));
                                // Check if we have the role from the join, otherwise fall back to relationship
                                $pivotRole = $character->static_role ?? $character->statics->firstWhere('id', $static->id)?->pivot?->role;
                                $pivotCombatRole = $character->statics->firstWhere('id', $static->id)?->pivot?->combat_role;
                            @endphp
                            <div class="px-8 py-4 grid grid-cols-12 items-center hover:bg-white/5 transition-colors group">
                                <!-- Character Column -->
                                <div class="col-span-4 flex items-center gap-4">
                                    <div class="relative">
                                        <img src="{{ $character->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($character->name) }}"
                                             class="w-12 h-12 rounded-full object-cover border border-white/5 shadow-lg group-hover:scale-105 transition-transform"
                                             alt="{{ $character->name }}">
                                        <div class="absolute -bottom-1 -right-1 w-3 h-3 bg-{{ $classColor }} rounded-full border-2 border-surface-container-high shadow-[0_0_10px_rgba(0,0,0,0.5)]"></div>
                                    </div>
                                    <div>
                                        <div class="font-headline font-bold text-{{ $classColor }} text-base tracking-tight leading-none mb-1 flex items-center gap-2">
                                            {{ $character->name }}
                                            @if($pivotRole === 'main')
                                                <span class="px-1.5 py-0.5 rounded-full bg-primary/20 text-primary border border-primary/30 text-[8px] uppercase tracking-widest font-black">{{ __('Main') }}</span>
                                            @endif
                                        </div>
                                        <div class="text-[10px] text-on-surface-variant font-bold uppercase tracking-widest flex items-center gap-2">
                                            <span>{{ __('Level') }} {{ $character->level }}</span>
                                            <span class="w-1 h-1 rounded-full bg-white/10"></span>
                                            @php
                                                $displaySpec = $character->active_spec;
                                                if ($displaySpec && str_starts_with($displaySpec, '{')) {
                                                    $decoded = json_decode($displaySpec, true);
                                                    $displaySpec = $decoded['name'] ?? $displaySpec;
                                                }
                                            @endphp
                                            <span>{{ $displaySpec ?? $character->playable_class }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Item Level Column -->
                                <div class="col-span-2 text-center">
                                    <span class="font-headline font-black text-2xl tracking-tighter text-on-surface">
                                        {{ $character->equipped_item_level ?? '?' }}
                                    </span>
                                </div>

                                <!-- Realm Column -->
                                <div class="col-span-2 text-on-surface-variant text-sm font-medium">
                                    {{ $character->realm->name }}
                                </div>

                                <!-- Main Radio Column -->
                                <div class="col-span-1 flex justify-center">
                                    <label class="relative flex items-center justify-center cursor-pointer group/radio">
                                        <input type="radio" name="main_character_id" value="{{ $character->id }}"
                                               {{ $pivotRole === 'main' ? 'checked' : '' }}
                                               class="peer sr-only">
                                        <div class="w-5 h-5 rounded-full border-2 border-white/10 peer-checked:border-primary peer-checked:bg-primary/20 transition-all"></div>
                                        <div class="absolute w-2 h-2 rounded-full bg-primary opacity-0 peer-checked:opacity-100 transition-all glow-primary"></div>
                                    </label>
                                </div>

                                <!-- Alt Checkbox Column -->
                                <div class="col-span-1 flex justify-center">
                                    <label class="relative flex items-center justify-center cursor-pointer group/check">
                                        <input type="checkbox" name="raiding_characters[]" value="{{ $character->id }}"
                                               {{ $pivotRole === 'alt' || $pivotRole === 'main' ? 'checked' : '' }}
                                               class="peer sr-only">
                                        <div class="w-5 h-5 rounded border-2 border-white/10 peer-checked:border-success-neon peer-checked:bg-success-neon/20 transition-all"></div>
                                        <span class="material-symbols-outlined absolute text-success-neon text-sm opacity-0 peer-checked:opacity-100 transition-all" style="font-variation-settings: 'FILL' 1;">check</span>
                                    </label>
                                </div>

                                <!-- Role Selection Column -->
                                <div class="col-span-2">
                                    <div class="relative">
                                        <select name="combat_roles[{{ $character->id }}]"
                                                class="w-full bg-black/40 border border-white/5 focus:ring-1 focus:ring-primary text-white font-bold text-[10px] py-2 px-3 rounded-md transition-all appearance-none cursor-pointer hover:bg-black/60 uppercase tracking-widest">
                                            <option value="tank" {{ $pivotCombatRole === 'tank' ? 'selected' : '' }}>{{ __('Tank') }}</option>
                                            <option value="heal" {{ $pivotCombatRole === 'heal' ? 'selected' : '' }}>{{ __('Healer') }}</option>
                                            <option value="mdps" {{ $pivotCombatRole === 'mdps' ? 'selected' : '' }}>{{ __('Melee DPS') }}</option>
                                            <option value="rdps" {{ $pivotCombatRole === 'rdps' ? 'selected' : '' }}>{{ __('Ranged DPS') }}</option>
                                        </select>
                                        <span class="material-symbols-outlined absolute right-2 top-1/2 -translate-y-1/2 text-on-surface-variant text-xs pointer-events-none">expand_more</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Table Footer / Action Area -->
                    <div class="p-6 bg-black/20 text-center border-t border-white/5">
                        <p class="text-[10px] font-bold uppercase tracking-[0.3em] text-on-surface-variant flex items-center justify-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-success-neon animate-pulse"></span>
                            {{ __('Status: Ready to Sync • Changes are saved automatically') }}
                        </p>
                    </div>
                </div>

                <!-- Section: Analytics & Balance (From Screenshot) -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-12 pb-24">
                    <!-- Tactical Analytics -->
                    <div class="bg-surface-container-high p-8 rounded-xl relative overflow-hidden group border border-white/5">
                        <div class="absolute top-0 right-0 w-64 h-64 bg-primary/5 blur-[100px] -mr-32 -mt-32"></div>
                        <h3 class="font-headline text-primary text-xs font-bold uppercase tracking-[0.3em] mb-8">{{ __('Tactical Analytics') }}</h3>
                        <div class="flex gap-16">
                            <div>
                                <div class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest mb-1">{{ __('Average iLevel') }}</div>
                                <div class="text-4xl font-headline font-black text-white">
                                    {{ number_format($characters->whereIn('id', $characters->pluck('id'))->avg('equipped_item_level') ?: 0, 1) }}
                                </div>
                            </div>
                            <div>
                                <div class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest mb-1">{{ __('Alt Synergy') }}</div>
                                <div class="text-4xl font-headline font-black text-white">84%</div>
                            </div>
                        </div>
                    </div>

                    <!-- Roster Balance -->
                    <div class="bg-surface-container-high p-8 rounded-xl relative overflow-hidden group border border-white/5">
                        <h3 class="font-headline text-on-surface text-xs font-bold uppercase tracking-[0.3em] mb-6 flex items-center gap-2">
                            {{ __('Roster Balance') }}
                            <span class="w-12 h-0.5 bg-white/10 rounded-full"></span>
                        </h3>
                        <p class="text-on-surface-variant text-xs font-medium max-w-sm leading-relaxed mb-6">
                            {{ __('Your current selection provides coverage for 4 major roles across different armor types.') }}
                        </p>
                        <div class="flex gap-2">
                            <div class="w-10 h-10 rounded-full bg-[#fa7902] flex items-center justify-center font-headline font-bold text-xs text-white shadow-[0_0_15px_rgba(250,121,2,0.3)]">D</div>
                            <div class="w-10 h-10 rounded-full bg-[#fffadf] flex items-center justify-center font-headline font-bold text-xs text-[#4c4800] shadow-[0_0_15px_rgba(255,250,223,0.3)]">R</div>
                            <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center font-headline font-bold text-xs text-on-primary shadow-[0_0_15px_rgba(79,211,247,0.3)]">M</div>
                        </div>
                    </div>
                </div>

                <!-- Fixed Bottom Save Bar (Visual Only for matching screenshot) -->
                <div class="fixed bottom-0 right-0 left-64 bg-[#0e0e10]/90 backdrop-blur-xl border-t border-white/5 px-12 py-6 flex justify-between items-center z-40">
                    <div class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">
                        {{ __('Status: Ready to Sync') }} <br>
                        <span class="text-[8px] opacity-50">Last updated 4 minutes ago</span>
                    </div>
                    <button type="submit" class="bg-primary text-on-primary px-12 py-4 font-headline font-bold text-sm uppercase tracking-widest rounded-sm hover:brightness-110 active:scale-95 transition-all flex items-center gap-3">
                        <span class="material-symbols-outlined">save</span>
                        {{ __('Save Roster Preferences') }}
                    </button>
                </div>
            </form>
        @endif
    </div>
</x-app-layout>
