<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>
        <link rel="icon" href="/images/logo.svg" type="image/svg+xml">

        <!-- Fonts -->
        <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;700;900&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Wowhead Tooltips -->
        <script>const whTooltips = {colorLinks: true, iconizeLinks: false, renameLinks: false};</script>
        <script src="https://wow.zamimg.com/js/tooltips.js"></script>
    </head>
    <body class="bg-background text-on-background min-h-screen arcane-bg antialiased">
        @php
            $static = Auth::user() ? Auth::user()->statics->first() : null;
        @endphp
        <!-- TopNavBar -->
        <header class="fixed top-0 z-50 w-full flex justify-between items-center px-6 py-4 bg-[#0e0e10]/80 backdrop-blur-xl shadow-[0_20px_40px_rgba(0,0,0,0.4)]">
            <div class="flex items-center gap-8">
                <div class="flex items-center gap-3">
                    <img src="/images/logo.svg" alt="BlastR Logo" class="h-8 w-auto drop-shadow-[0_0_8px_rgba(58,223,250,0.5)]" />
                    <div class="text-2xl font-black uppercase tracking-tighter italic leading-none">
                        <span class="text-white">Blast</span><span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-cyan-400">R<span class="text-xs opacity-70 ml-0.5">r<span class="text-[10px] opacity-50 ml-0.5">r</span></span></span>
                    </div>
                </div>
                @if($static)
                    <div class="hidden md:flex items-center gap-4 ml-4">
                        <button onclick="handleInviteClick({{ $static->id }})"
                                class="flex items-center gap-2 px-4 py-1.5 bg-cyan-500/10 border border-cyan-500/50 text-cyan-400 rounded-md hover:bg-cyan-500 hover:text-white transition-all active:scale-95 group">
                            <span class="material-symbols-outlined text-sm">person_add</span>
                            <span class="font-headline text-[10px] font-bold uppercase tracking-widest">Invite to Group</span>
                        </button>
                        <div id="invite-toast" class="hidden fixed bottom-6 right-6 bg-cyan-600 text-white px-6 py-3 rounded-lg shadow-2xl animate-bounce font-headline text-xs font-bold uppercase tracking-widest z-[100]">
                            Invite Link Copied!
                        </div>
                    </div>

                    <script>
                        async function handleInviteClick(staticId) {
                            try {
                                const response = await fetch(`/statics/${staticId}/invite`, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                    }
                                });
                                const data = await response.json();
                                if (data.link) {
                                    copyToClipboard(data.link);
                                }
                            } catch (error) {
                                console.error('Error generating invite link:', error);
                            }
                        }

                        function copyToClipboard(link) {
                            navigator.clipboard.writeText(link).then(() => {
                                const toast = document.getElementById('invite-toast');
                                toast.classList.remove('hidden');
                                setTimeout(() => {
                                    toast.classList.add('hidden');
                                }, 3000);
                            });
                        }
                    </script>
                @endif
            </div>
            <div class="flex items-center gap-4">
                <!-- Settings Dropdown -->
                <x-dropdown align="right" width="48" contentClasses="py-1 bg-surface-container-highest border border-white/10 shadow-2xl">
                    <x-slot name="trigger">
                        <button class="h-10 w-10 rounded-full overflow-hidden border border-outline-variant hover:border-primary transition-all active:scale-95">
                            <img alt="User Avatar" class="w-full h-full object-cover rounded-full" src="{{ Auth::user()->getEffectiveAvatarUrl($static->id ?? null) }}"/>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <div class="px-4 py-2 border-b border-white/5">
                            <div class="font-headline text-xs font-bold text-white uppercase tracking-widest">{{ Auth::user()->name }}</div>
                            <div class="text-[10px] text-gray-500 font-medium truncate">{{ Auth::user()->email }}</div>
                        </div>

                        <x-dropdown-link :href="route('profile.edit')" class="block w-full px-4 py-2 text-start font-headline text-[10px] font-bold uppercase tracking-widest text-gray-400 hover:text-cyan-400 hover:bg-white/5 transition-colors">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <div class="px-4 py-2 border-y border-white/5 bg-black/10">
                            <div class="font-headline text-[8px] font-bold text-gray-500 uppercase tracking-[0.2em] mb-2">{{ __('My Characters') }}</div>
                            <div class="space-y-1">
                                @forelse(Auth::user()->characters->take(5) as $character)
                                    <div class="flex items-center gap-2 py-1">
                                        <div class="relative shrink-0">
                                            <img src="{{ $character->avatar_url }}" class="w-8 h-8 rounded-full border border-white/10" alt="">
                                            <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-surface-container rounded-full border border-white/10 flex items-center justify-center overflow-hidden">
                                                <img src="{{ $character->getClassIconUrl() }}" class="w-2.5 h-2.5" alt="">
                                            </div>
                                        </div>
                                        <span class="text-[10px] font-bold text-{{ strtolower(str_replace(' ', '-', $character->playable_class)) }} truncate">{{ $character->name }}</span>
                                    </div>
                                @empty
                                    <div class="text-[9px] text-gray-600 italic tracking-wider">{{ __('No characters') }}</div>
                                @endforelse
                                @if(Auth::user()->characters->count() > 5)
                                    <a href="{{ route('characters.index') }}" class="block text-[8px] font-bold text-cyan-400/60 hover:text-cyan-400 uppercase tracking-widest mt-1 transition-colors">{{ __('View All') }}</a>
                                @endif
                            </div>
                        </div>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault(); this.closest('form').submit();"
                                    class="block w-full px-4 py-2 text-start font-headline text-[10px] font-bold uppercase tracking-widest text-gray-400 hover:text-red-400 hover:bg-white/5 transition-colors">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>
        </header>

        <!-- SideNavBar -->
        <aside class="fixed left-0 top-0 h-screen w-64 bg-[#131315] border-r border-white/5 pt-24 hidden lg:flex flex-col">
            @if($static)
                <div class="px-6 mb-8">
                    <h2 class="font-headline text-lg font-bold text-white uppercase tracking-tighter">{{ $static->name }}</h2>
                    <p class="text-xs font-bold text-cyan-400 uppercase tracking-widest mt-1">Mythic Progression</p>
                </div>

                <div class="flex-1 overflow-y-auto px-3 space-y-1">
                    <a href="{{ route('statics.dashboard', $static->id) }}"
                       class="w-full flex items-center gap-3 px-4 py-2.5 group transition-all {{ request()->routeIs('statics.dashboard') ? 'bg-[#262528] text-white border-l-4 border-cyan-400' : 'text-gray-500 hover:text-gray-300 hover:bg-[#1f1f22] hover:translate-x-1' }}">
                        <span class="material-symbols-outlined {{ request()->routeIs('statics.dashboard') ? 'text-cyan-400' : 'group-hover:text-cyan-400 transition-colors' }}">dashboard</span>
                        <span class="font-headline text-xs font-bold uppercase tracking-widest">Dashboard</span>
                    </a>

                    <a href="{{ route('statics.roster', $static->id) }}"
                       class="w-full flex items-center gap-3 px-4 py-2.5 group transition-all {{ request()->routeIs('statics.roster') ? 'bg-[#262528] text-white border-l-4 border-cyan-400' : 'text-gray-500 hover:text-gray-300 hover:bg-[#1f1f22] hover:translate-x-1' }}">
                        <span class="material-symbols-outlined {{ request()->routeIs('statics.roster') ? 'text-cyan-400' : 'group-hover:text-cyan-400 transition-colors' }}">groups</span>
                        <span class="font-headline text-xs font-bold uppercase tracking-widest">Roster</span>
                    </a>

                    <a href="{{ route('consumables.index') }}"
                       class="w-full flex items-center gap-3 px-4 py-2.5 group transition-all {{ request()->routeIs('consumables.*') ? 'bg-[#262528] text-white border-l-4 border-cyan-400' : 'text-gray-500 hover:text-gray-300 hover:bg-[#1f1f22] hover:translate-x-1' }}">
                        <span class="material-symbols-outlined {{ request()->routeIs('consumables.*') ? 'text-cyan-400' : 'group-hover:text-cyan-400 transition-colors' }}">inventory_2</span>
                        <span class="font-headline text-xs font-bold uppercase tracking-widest">Consumables</span>
                    </a>

                    <a href="{{ route('schedule.index') }}"
                       class="w-full flex items-center gap-3 px-4 py-2.5 group transition-all {{ request()->routeIs('schedule.*') ? 'bg-[#262528] text-white border-l-4 border-cyan-400' : 'text-gray-500 hover:text-gray-300 hover:bg-[#1f1f22] hover:translate-x-1' }}">
                        <span class="material-symbols-outlined {{ request()->routeIs('schedule.*') ? 'text-cyan-400' : 'group-hover:text-cyan-400 transition-colors' }}">calendar_month</span>
                        <span class="font-headline text-xs font-bold uppercase tracking-widest">Schedule</span>
                    </a>

                    <a href="{{ route('statics.treasury', $static->id) }}"
                       class="w-full flex items-center gap-3 px-4 py-2.5 group transition-all {{ request()->routeIs('statics.treasury') ? 'bg-[#262528] text-white border-l-4 border-cyan-400' : 'text-gray-500 hover:text-gray-300 hover:bg-[#1f1f22] hover:translate-x-1' }}">
                        <span class="material-symbols-outlined {{ request()->routeIs('statics.treasury') ? 'text-cyan-400' : 'group-hover:text-cyan-400 transition-colors' }}">payments</span>
                        <span class="font-headline text-xs font-bold uppercase tracking-widest">Treasury</span>
                    </a>

                    <a href="{{ route('statics.logs.index', $static->id) }}"
                       class="w-full flex items-center gap-3 px-4 py-2.5 group transition-all {{ request()->routeIs('statics.logs.*') ? 'bg-[#262528] text-white border-l-4 border-amber-500' : 'text-gray-500 hover:text-gray-300 hover:bg-[#1f1f22] hover:translate-x-1' }}">
                        <span class="material-symbols-outlined {{ request()->routeIs('statics.logs.*') ? 'text-amber-500' : 'group-hover:text-amber-500 transition-colors' }}">terminal</span>
                        <span class="font-headline text-xs font-bold uppercase tracking-widest">Intelligence</span>
                    </a>

                    <div class="pt-4 pb-2 px-4">
                        <div class="h-px bg-white/5 w-full"></div>
                    </div>

                    <a href="{{ route('statics.settings.schedule', $static->id) }}"
                       class="w-full flex items-center gap-3 px-4 py-2.5 group transition-all {{ request()->routeIs('statics.settings.*') ? 'bg-[#262528] text-white border-l-4 border-cyan-400' : 'text-gray-500 hover:text-gray-300 hover:bg-[#1f1f22] hover:translate-x-1' }}">
                        <span class="material-symbols-outlined {{ request()->routeIs('statics.settings.*') ? 'text-cyan-400' : 'group-hover:text-cyan-400 transition-colors' }}">settings</span>
                        <span class="font-headline text-xs font-bold uppercase tracking-widest">Settings</span>
                    </a>
                </div>
            @else
                <div class="px-6 mb-8 flex items-center gap-3">
                    <img src="/images/logo.svg" alt="BlastR Logo" class="h-6 w-auto drop-shadow-[0_0_8px_rgba(58,223,250,0.5)]" />
                    <div class="text-lg font-black uppercase tracking-tighter italic leading-none">
                        <span class="text-white">Blast</span><span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-cyan-400">R<span class="text-[8px] opacity-70 ml-0.5">r<span class="text-[6px] opacity-50 ml-0.5">r</span></span></span>
                    </div>
                </div>
            @endif

            <div class="p-6 border-t border-white/5 space-y-4">
                <a href="{{ route('characters.index') }}" class="w-full block bg-primary text-center text-on-primary py-2 font-headline font-bold text-xs uppercase tracking-widest rounded-sm hover:brightness-110 active:scale-95 transition-all">My Characters</a>
                <div class="flex flex-col gap-2">
                    <a class="flex items-center gap-3 text-gray-500 hover:text-white transition-colors px-1" href="#">
                        <span class="material-symbols-outlined text-lg">help</span>
                        <span class="font-headline text-[10px] font-bold uppercase tracking-widest">Support</span>
                    </a>
                    <a class="flex items-center gap-3 text-gray-500 hover:text-white transition-colors px-1" href="#">
                        <span class="material-symbols-outlined text-lg">history</span>
                        <span class="font-headline text-[10px] font-bold uppercase tracking-widest">Archive</span>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Content Canvas -->
        <main class="lg:ml-64 pt-24 pb-12 px-8 min-h-screen" id="app">
            <div class="max-w-7xl mx-auto space-y-12">
                {{ $slot }}
            </div>
        </main>
    </body>
</html>
