<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-background text-on-background min-h-screen arcane-bg antialiased">
        @php
            $static = Auth::user() ? Auth::user()->statics->first() : null;
        @endphp
        <!-- TopNavBar -->
        <header class="fixed top-0 z-50 w-full flex justify-between items-center px-6 py-4 bg-[#0e0e10]/80 backdrop-blur-xl shadow-[0_20px_40px_rgba(0,0,0,0.4)]">
            <div class="flex items-center gap-8">
                <div class="text-xl font-black text-cyan-400 uppercase tracking-widest font-headline">Static Hub</div>
                <nav class="hidden md:flex gap-6 items-center">
                    <a class="font-headline font-bold tracking-tight {{ request()->routeIs('statics.dashboard') ? 'text-cyan-400 border-b-2 border-cyan-400 pb-1' : 'text-gray-400 hover:text-white transition-colors' }}" href="{{ $static ? route('statics.dashboard', $static->id) : '#' }}">Dashboard</a>
                    <a class="font-headline font-bold tracking-tight {{ request()->routeIs('statics.roster') ? 'text-cyan-400 border-b-2 border-cyan-400 pb-1' : 'text-gray-400 hover:text-white transition-colors' }}" href="{{ $static ? route('statics.roster', $static->id) : '#' }}">Roster</a>
                    <a class="font-headline font-bold tracking-tight {{ request()->routeIs('consumables.*') ? 'text-cyan-400 border-b-2 border-cyan-400 pb-1' : 'text-gray-400 hover:text-white transition-colors' }}" href="{{ route('consumables.index') }}">Consumables</a>
                    <a class="font-headline font-bold tracking-tight {{ request()->routeIs('schedule.*') ? 'text-cyan-400 border-b-2 border-cyan-400 pb-1' : 'text-gray-400 hover:text-white transition-colors' }}" href="{{ route('schedule.index') }}">Schedule</a>
                    <a class="font-headline font-bold tracking-tight text-gray-400 hover:text-white transition-colors" href="#">Loot</a>
                </nav>
            </div>
            <div class="flex items-center gap-4">
                <button class="material-symbols-outlined text-on-surface-variant hover:text-primary transition-colors">notifications</button>
                <button class="material-symbols-outlined text-on-surface-variant hover:text-primary transition-colors">settings</button>

                <!-- Settings Dropdown -->
                <x-dropdown align="right" width="48" contentClasses="py-1 bg-surface-container-highest border border-white/10 shadow-2xl">
                    <x-slot name="trigger">
                        <button class="h-10 w-10 rounded-lg overflow-hidden border border-outline-variant hover:border-primary transition-all active:scale-95">
                            <img alt="User Avatar" class="w-full h-full object-cover" src="{{ Auth::user()->avatar ?? 'https://ui-avatars.com/api/?name='.urlencode(Auth::user()->name) }}"/>
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
                                        <img src="{{ $character->avatar_url }}" class="w-4 h-4 rounded-full border border-white/10" alt="">
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
                </div>
            @else
                <div class="px-6 mb-8">
                    <h2 class="font-headline text-lg font-bold text-white uppercase tracking-tighter">Static Hub</h2>
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
        <main class="lg:ml-64 pt-24 pb-12 px-8 min-h-screen">
            <div class="max-w-7xl mx-auto space-y-12">
                {{ $slot }}
            </div>
        </main>
    </body>
</html>
