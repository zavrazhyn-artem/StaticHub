<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <div class="w-3 h-3 bg-success-neon rounded-full shadow-[0_0_8px_#39FF14]"></div>
                <h2 class="font-headline text-xl text-on-surface leading-tight tracking-tight uppercase">
                    Tactical Roster: {{ $static->name }}
                </h2>
            </div>
            <div class="flex space-x-2">
                <button class="bg-surface-container-high text-on-surface-variant hover:text-primary px-4 py-2 rounded text-xs font-label uppercase tracking-widest transition-colors border border-white/5">
                    Add Member
                </button>
                <a href="{{ route('statics.roster.overview', $static->id) }}" class="bg-cyan-400/10 text-cyan-400 border border-cyan-400/20 hover:bg-cyan-400/20 px-4 py-2 rounded text-xs font-label uppercase tracking-widest transition-colors">
                    Roster Overview
                </a>
                <button class="bg-primary/10 text-primary border border-primary/20 hover:bg-primary/20 px-4 py-2 rounded text-xs font-label uppercase tracking-widest transition-colors">
                    Edit Roles
                </button>
            </div>
        </div>
    </x-slot>

    @php
        $roles = [
            'tank' => ['label' => 'Tanks', 'icon' => 'shield', 'target' => 2],
            'heal' => ['label' => 'Healers', 'icon' => 'medical_services', 'target' => 4],
            'mdps' => ['label' => 'Melee DPS', 'icon' => 'swords', 'target' => 8],
            'rdps' => ['label' => 'Ranged DPS', 'icon' => 'target', 'target' => 6],
        ];

        $classColors = [
            'Death Knight' => 'text-[#C41F3B]',
            'Demon Hunter' => 'text-[#A330C9]',
            'Druid' => 'text-[#FF7C0A]',
            'Evoker' => 'text-[#33937F]',
            'Hunter' => 'text-[#ABD473]',
            'Mage' => 'text-[#3FC7EB]',
            'Monk' => 'text-[#00FF98]',
            'Paladin' => 'text-[#F48CBA]',
            'Priest' => 'text-[#FFFFFF]',
            'Rogue' => 'text-[#FFF468]',
            'Shaman' => 'text-[#0070DD]',
            'Warlock' => 'text-[#8788EE]',
            'Warrior' => 'text-[#C69B6D]',
        ];
    @endphp

    <div class="py-6">
        <div class="max-w-[1600px] mx-auto sm:px-6 lg:px-8 space-y-8">
            @foreach($roles as $roleKey => $roleData)
                <section>
                    <div class="flex items-center justify-between mb-4 border-b border-white/5 pb-2">
                        <div class="flex items-center space-x-3">
                            <span class="material-symbols-outlined text-primary text-xl">{{ $roleData['icon'] }}</span>
                            <h3 class="font-headline text-lg text-on-surface uppercase tracking-wider">{{ $roleData['label'] }}</h3>
                            <span class="text-xs font-label text-on-surface-variant bg-surface-container-highest px-2 py-0.5 rounded">
                                {{ count($groupedRoster[$roleKey] ?? []) }} / {{ $roleData['target'] }}
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                        @foreach($groupedRoster[$roleKey] ?? [] as $user)
                            <div class="bg-surface-container-high border border-white/5 rounded-lg p-4 hover:border-primary/30 transition-all group relative overflow-hidden">
                                <div class="absolute top-0 right-0 p-2 opacity-20 group-hover:opacity-100 transition-opacity">
                                    <span class="material-symbols-outlined text-on-surface-variant cursor-pointer hover:text-primary text-sm">settings</span>
                                </div>

                                <div class="flex items-start space-x-4">
                                    <div class="relative">
                                        @if($user->mainCharacter?->avatar_url)
                                            <img src="{{ $user->mainCharacter->avatar_url }}" class="w-12 h-12 rounded border border-white/10" alt="">
                                        @else
                                            <div class="w-12 h-12 bg-surface-container-highest rounded border border-white/10 flex items-center justify-center">
                                                <span class="material-symbols-outlined text-on-surface-variant">person</span>
                                            </div>
                                        @endif
                                        <div class="absolute -bottom-1 -right-1 w-5 h-5 bg-surface-container-highest rounded-full border border-white/10 flex items-center justify-center">
                                            <span class="material-symbols-outlined text-[12px] text-primary">{{ $roleData['icon'] }}</span>
                                        </div>
                                    </div>

                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            @php
                                                $tax = $weeklyTaxStatus[$user->id] ?? ['is_paid' => false];
                                            @endphp
                                            <span class="material-symbols-outlined text-[14px] {{ $tax['is_paid'] ? 'text-success-neon' : 'text-error' }}"
                                                  title="{{ $tax['is_paid'] ? 'Paid Weekly Tax (' . \App\Helpers\CurrencyHelper::formatGold($targetTax) . ')' : 'Outstanding Weekly Tax (' . \App\Helpers\CurrencyHelper::formatGold($targetTax) . ')' }}">
                                                attach_money
                                            </span>
                                            <h4 class="font-headline text-md truncate {{ $classColors[$user->mainCharacter->playable_class] ?? 'text-white' }}">
                                                {{ $user->mainCharacter->name }}
                                            </h4>
                                        </div>
                                        <p class="text-[10px] font-label text-on-surface-variant uppercase tracking-widest">
                                            {{ $user->mainCharacter->playable_class }} • {{ $user->mainCharacter->active_spec ?? 'Main' }}
                                        </p>
                                    </div>
                                </div>

                                @if($user->altCharacters && $user->altCharacters->count() > 0)
                                    <div class="mt-4 pt-3 border-t border-white/5 flex items-center space-x-2">
                                        <span class="text-[9px] font-label text-on-surface-variant uppercase tracking-tighter">ALTS:</span>
                                        <div class="flex -space-x-1">
                                            @foreach($user->altCharacters as $alt)
                                                <div class="w-5 h-5 rounded-full border border-surface-container-high bg-surface-container-highest overflow-hidden cursor-help group/alt relative" title="{{ $alt->name }} ({{ $alt->playable_class }})">
                                                    @if($alt->avatar_url)
                                                        <img src="{{ $alt->avatar_url }}" class="w-full h-full object-cover grayscale group-hover/alt:grayscale-0 transition-all" alt="">
                                                    @else
                                                        <div class="w-full h-full flex items-center justify-center text-[8px] text-on-surface-variant">?</div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach

                        @for($i = count($groupedRoster[$roleKey] ?? []); $i < $roleData['target']; $i++)
                            <div class="border border-dashed border-white/10 rounded-lg p-4 flex flex-col items-center justify-center bg-white/5 hover:bg-white/10 transition-colors cursor-pointer group">
                                <span class="material-symbols-outlined text-on-surface-variant/30 group-hover:text-primary transition-colors text-3xl mb-2">add_circle</span>
                                <span class="text-[10px] font-label text-on-surface-variant uppercase tracking-widest">Recruiting: {{ $roleKey }}</span>
                            </div>
                        @endfor
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</x-app-layout>
