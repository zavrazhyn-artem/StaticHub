@php use App\Policies\StaticGroupPolicy; @endphp
    <!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>
    <link rel="icon" href="/images/logo.svg" type="image/svg+xml">

    <!-- All fonts (Inter/Space Grotesk/Orbitron/Material Symbols) bundled via app.css -->

    <script>
        // Читаємо JSON з перекладами і записуємо в глобальну змінну вікна
        window.translations = {!! file_exists(base_path('lang/' . app()->getLocale() . '.json'))
            ? file_get_contents(base_path('lang/' . app()->getLocale() . '.json'))
            : '{}' !!};
        window.appLocale = @json(app()->getLocale());
    </script>
    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Wowhead Tooltips -->
    <script>const whTooltips = {colorLinks: true, iconizeLinks: false, renameLinks: false};</script>
    <script src="https://wow.zamimg.com/js/tooltips.js"></script>
</head>
<body class="bg-background text-on-background min-h-screen arcane-bg subpixel-antialiased" x-data="{ sidebarOpen: false }">
@php
    $ghostService = app(\App\Services\Ghost\GhostModeService::class);
    $ghostActive = $ghostService->isActive();

    if ($ghostActive) {
        $static = \App\Models\StaticGroup::withoutGlobalScopes()->find($ghostService->currentStaticId());
    } else {
        $static = Auth::user() ? Auth::user()->statics->first() : null;
    }
    $isOnboarding = $onboarding ?? false;
    $isBare = $bare ?? false;
    // "Back to Blastr" (on bare pages like /feedback) is only meaningful for
    // fully-onboarded users: logged in + has a static + picked a main character.
    // Anyone less than that would be bounced to onboarding, which is the opposite
    // of what "back to the app" should feel like.
    $canReturnToBlastr = Auth::check()
        && $static
        && \App\Models\User::query()->hasMainCharacter(Auth::id());
@endphp
    <!-- TopNavBar -->
<header
    class="fixed top-0 z-50 w-full flex justify-between items-center px-6 py-4 bg-[#0e0e10]/80 backdrop-blur-xl shadow-[0_20px_40px_rgba(0,0,0,0.4)]">
    <div class="flex items-center gap-4 lg:gap-8">
        @if($static && !$isOnboarding && !$isBare)
            <button @click="sidebarOpen = !sidebarOpen"
                    type="button"
                    class="lg:hidden flex items-center justify-center h-10 w-10 -ml-2 rounded-md text-gray-300 hover:text-white hover:bg-white/5 active:scale-95 transition-all"
                    :aria-expanded="sidebarOpen"
                    aria-label="{{ __('Toggle menu') }}">
                <span class="material-symbols-outlined" x-text="sidebarOpen ? 'close' : 'menu'">menu</span>
            </button>
        @endif
        <div class="flex items-center gap-3">
            <img src="/images/logo.svg" alt="BlastR Logo"
                 class="h-8 w-auto drop-shadow-[0_0_8px_rgba(58,223,250,0.5)]"/>
            <div class="text-2xl font-black uppercase tracking-tighter italic leading-none">
                <span class="text-white">Blast</span><span
                    class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-cyan-400">R<span
                        class="text-xs opacity-70 ml-0.5">r<span
                            class="text-3xs opacity-50 ml-0.5">r</span></span></span>
            </div>
        </div>
        @if($isBare)
            {{-- On bare pages (feedback/roadmap/etc.): only fully-onboarded users
                 get the "Back to Blastr" escape hatch. Guests and half-set-up
                 users see nothing here. Invite-to-Group is never shown on bare. --}}
            @if($canReturnToBlastr)
                <a href="{{ route('dashboard') }}"
                   class="hidden md:flex items-center gap-2 px-4 py-1.5 ml-4 bg-cyan-500/10 border border-cyan-500/50 text-cyan-400 rounded-md hover:bg-cyan-500 hover:text-white transition-all active:scale-95">
                    <span class="material-symbols-outlined text-sm">arrow_back</span>
                    <span class="text-3xs font-semibold uppercase tracking-wider">{{ __('Back to Blastr') }}</span>
                </a>
            @endif
        @elseif($static && !$isOnboarding)
            @can('manage', $static)
            <div class="hidden md:flex items-center gap-4 ml-4">
                <button onclick="handleInviteClick()"
                        class="flex items-center gap-2 px-4 py-1.5 bg-cyan-500/10 border border-cyan-500/50 text-cyan-400 rounded-md hover:bg-cyan-500 hover:text-white transition-all active:scale-95 group">
                    <span class="material-symbols-outlined text-sm">person_add</span>
                    <span
                        class="text-3xs font-semibold uppercase tracking-wider">{{ __("Invite to Group") }}</span>
                </button>
                <div id="invite-toast"
                     class="hidden fixed bottom-6 right-6 bg-cyan-600 text-white px-6 py-3 rounded-lg shadow-2xl animate-bounce font-headline text-xs font-bold uppercase tracking-widest z-[100]">
                    Invite Link Copied!
                </div>
            </div>
            @endcan

            <script>
                async function handleInviteClick() {
                    try {
                        const response = await fetch(`/invite`, {
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

    @if($static && !$isOnboarding && Auth::check() && !Auth::user()->discord_id)
            <div id="discord-link-banner"
                 class="hidden items-center gap-3 px-4 py-2 bg-amber-500/10 border border-amber-500/30 rounded-lg backdrop-blur-sm text-amber-400">
                <span class="material-symbols-outlined text-base leading-none">link_off</span>
                <span class="text-3xs font-semibold uppercase tracking-wider leading-none">{{ __('Link your Discord for bot commands, RSVP & notifications') }}</span>
                <a href="{{ route('profile.discord.link') }}"
                   class="ml-2 text-3xs font-semibold uppercase tracking-wider leading-none px-3 py-1.5 rounded-sm bg-amber-500 hover:bg-amber-400 text-black transition-all active:scale-95 flex items-center">
                    {{ __('Link Discord') }}
                </a>
                <button onclick="dismissDiscordBanner()"
                        class="ml-1 opacity-60 hover:opacity-100 transition-opacity flex items-center">
                    <span class="material-symbols-outlined text-sm leading-none">close</span>
                </button>
            </div>
            <script>
                (function() {
                    if (!localStorage.getItem('alert_dismissed_discord-link-reminder')) {
                        document.getElementById('discord-link-banner').classList.remove('hidden');
                        document.getElementById('discord-link-banner').classList.add('flex');
                    }
                })();
                function dismissDiscordBanner() {
                    localStorage.setItem('alert_dismissed_discord-link-reminder', '1');
                    document.getElementById('discord-link-banner').classList.add('hidden');
                    document.getElementById('discord-link-banner').classList.remove('flex');
                }
            </script>
    @endif

    <div class="flex items-center gap-4">
        @if($ghostActive)
            <!-- Ghost Mode Badge + Exit -->
            <div class="hidden md:flex items-center gap-2 px-3 py-1.5 bg-fuchsia-500/10 border border-fuchsia-500/40 rounded-md">
                <span class="material-symbols-outlined text-fuchsia-400 text-sm">visibility</span>
                <span class="text-3xs font-semibold uppercase tracking-wider text-fuchsia-300">
                    {{ __('Ghost') }}:&nbsp;{{ $static?->name }}
                </span>
            </div>
            <form action="{{ route('admin.ghost.exit') }}" method="POST">
                @csrf
                <button type="submit"
                        class="flex items-center gap-2 px-3 py-1.5 bg-fuchsia-500/10 border border-fuchsia-500/50 text-fuchsia-300 rounded-md hover:bg-fuchsia-500 hover:text-white transition-all active:scale-95">
                    <span class="material-symbols-outlined text-sm">arrow_back</span>
                    <span class="text-3xs font-semibold uppercase tracking-wider">{{ __('Back to Admin') }}</span>
                </button>
            </form>
        @endif

        <!-- Language Selector -->
        @php
            $localeMap = [
                'en' => ['country' => 'GB', 'label' => 'English'],
                'uk' => ['country' => 'UA', 'label' => 'Українська'],
            ];
            $availableLocales = collect(glob(base_path('lang/*.json')))
                ->map(fn($f) => pathinfo($f, PATHINFO_FILENAME))
                ->filter(fn($l) => isset($localeMap[$l]));
            $currentLocale = app()->getLocale();
            $currentCountry = $localeMap[$currentLocale]['country'] ?? 'US';
        @endphp
        <div class="relative group">
            <button
                class="flex items-center gap-2 px-3 py-1.5 bg-white/5 border border-white/10 rounded-md hover:bg-white/10 transition-all text-xs font-bold uppercase tracking-widest text-gray-400 hover:text-white">
                <img src="/images/flags/{{ $currentCountry }}.svg" alt="{{ $currentCountry }}"
                     class="w-5 h-auto rounded-sm">
                <span class="material-symbols-outlined text-sm">expand_more</span>
            </button>
            <div
                class="absolute right-0 mt-2 w-36 py-1 bg-surface-container-highest border border-white/10 shadow-2xl rounded-md opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50">
                <form action="{{ route('language.switch') }}" method="POST">
                    @csrf
                    @foreach($availableLocales as $locale)
                        @php $info = $localeMap[$locale]; @endphp
                        <button name="locale" value="{{ $locale }}"
                                class="flex items-center gap-3 w-full px-4 py-2 text-start text-3xs font-semibold uppercase tracking-wider text-gray-400 hover:text-cyan-400 hover:bg-white/5 transition-colors {{ $currentLocale === $locale ? 'text-cyan-400 bg-white/5' : '' }}">
                            <img src="/images/flags/{{ $info['country'] }}.svg" alt="{{ $info['country'] }}"
                                 class="w-5 h-auto rounded-sm">
                            {{ $info['label'] }}
                        </button>
                    @endforeach
                </form>
            </div>
        </div>

        @unless($isOnboarding)
            @auth
                <!-- Settings Dropdown -->
                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                    <div @click="open = !open">
                        <button
                            class="h-10 w-10 rounded-full overflow-hidden border border-outline-variant hover:border-primary transition-all active:scale-95">
                            <img alt="User Avatar" class="w-full h-full object-cover rounded-full"
                                 src="{{ Auth::user()->getEffectiveAvatarUrl($static->id ?? null) }}"/>
                        </button>
                    </div>

                    <div
                        x-show="open"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        class="absolute z-50 mt-2 w-48 rounded-md shadow-lg ltr:origin-top-right rtl:origin-top-left end-0"
                        style="display: none;"
                        @click="open = false"
                    >
                        <div
                            class="rounded-md ring-1 ring-black ring-opacity-5 py-1 bg-surface-container-highest border border-white/10 shadow-2xl">
                            <div class="px-4 py-2 border-b border-white/5">
                                <div
                                    class="font-headline text-xs font-bold text-white uppercase tracking-widest">{{ Auth::user()->name }}</div>
                                <div class="text-3xs text-gray-400 font-medium truncate">{{ Auth::user()->email }}</div>
                            </div>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <a href="{{ route('logout') }}"
                                   onclick="event.preventDefault(); this.closest('form').submit();"
                                   class="block w-full px-4 py-2 text-start text-3xs font-semibold uppercase tracking-wider text-gray-400 hover:text-red-400 hover:bg-white/5 transition-colors">
                                    {{ __('Log Out') }}
                                </a>
                            </form>
                        </div>
                    </div>
                </div>
            @else
                @php
                    $currentPath = request()->getRequestUri();
                    $signInHref = route('battlenet.redirect');
                    if ($isBare && $currentPath) {
                        $signInHref .= '?redirect_to=' . urlencode($currentPath);
                    }
                @endphp
                <a href="{{ $signInHref }}"
                   class="flex items-center gap-2 px-4 py-2 bg-cyan-500/10 border border-cyan-500/50 text-cyan-400 rounded-md hover:bg-cyan-500 hover:text-white transition-all active:scale-95">
                    <span class="material-symbols-outlined text-sm">login</span>
                    <span class="text-3xs font-semibold uppercase tracking-wider">{{ __('Sign in') }}</span>
                </a>
            @endauth
        @endunless
    </div>
</header>

@if($static && !$isOnboarding && !$isBare)
    <!-- Mobile Sidebar Backdrop -->
    <div x-show="sidebarOpen"
         x-transition:enter="transition-opacity ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="sidebarOpen = false"
         class="fixed inset-0 bg-black/60 backdrop-blur-sm z-30 lg:hidden"
         style="display: none;"></div>
@endif

<!-- SideNavBar -->
<aside class="fixed left-0 top-0 h-screen w-64 bg-[#131315] border-r border-white/5 pt-24 flex-col z-40 {{ ($isOnboarding || $isBare) ? 'hidden' : ($static ? 'flex transform transition-transform duration-300 ease-in-out lg:translate-x-0' : 'hidden lg:flex') }}"
       @if($static && !$isOnboarding && !$isBare) :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" @endif>
    @if($static)
        <div class="px-6 mb-8">
            <h2 class="font-headline text-lg font-bold text-white uppercase tracking-tight">{{ $static->name }}</h2>
            <p class="text-xs font-bold text-cyan-400 uppercase tracking-widest mt-1">Mythic Progression</p>
        </div>

        <div class="flex-1 overflow-y-auto px-3 space-y-1">
            <a href="{{ route('dashboard') }}"
               class="w-full flex items-center gap-3 px-4 py-2.5 group transition-all {{ request()->routeIs('dashboard') ? 'bg-[#262528] text-white border-l-4 border-cyan-400' : 'text-gray-400 hover:text-gray-300 hover:bg-[#1f1f22] hover:translate-x-1' }}">
                <span
                    class="material-symbols-outlined {{ request()->routeIs('dashboard') ? 'text-cyan-400' : 'group-hover:text-cyan-400 transition-colors' }}">dashboard</span>
                <span class="font-nav text-xs font-bold uppercase tracking-widest">{{ __('Dashboard') }}</span>
            </a>

            <a href="{{ route('statics.roster') }}"
               class="w-full flex items-center gap-3 px-4 py-2.5 group transition-all {{ request()->routeIs('statics.roster') ? 'bg-[#262528] text-white border-l-4 border-emerald-400' : 'text-gray-400 hover:text-gray-300 hover:bg-[#1f1f22] hover:translate-x-1' }}">
                <span
                    class="material-symbols-outlined {{ request()->routeIs('statics.roster') ? 'text-emerald-400' : 'group-hover:text-emerald-400 transition-colors' }}">groups</span>
                <span class="font-nav text-xs font-bold uppercase tracking-widest">{{ __('Roster') }}</span>
            </a>


            <a href="{{ route('schedule.index') }}"
               class="w-full flex items-center gap-3 px-4 py-2.5 group transition-all {{ request()->routeIs('schedule.*') ? 'bg-[#262528] text-white border-l-4 border-fuchsia-400' : 'text-gray-400 hover:text-gray-300 hover:bg-[#1f1f22] hover:translate-x-1' }}">
                <span
                    class="material-symbols-outlined {{ request()->routeIs('schedule.*') ? 'text-fuchsia-400' : 'group-hover:text-fuchsia-400 transition-colors' }}">calendar_month</span>
                <span class="font-nav text-xs font-bold uppercase tracking-widest">{{ __('Schedule') }}</span>
            </a>

            <a href="{{ route('statics.boss-planner') }}"
               class="w-full flex items-center gap-3 px-4 py-2.5 group transition-all {{ request()->routeIs('statics.boss-planner*') ? 'bg-[#262528] text-white border-l-4 border-orange-500' : 'text-gray-400 hover:text-gray-300 hover:bg-[#1f1f22] hover:translate-x-1' }}">
                <span
                    class="material-symbols-outlined {{ request()->routeIs('statics.boss-planner*') ? 'text-orange-500' : 'group-hover:text-orange-500 transition-colors' }}">map</span>
                <span class="font-nav text-xs font-bold uppercase tracking-widest">{{ __('Boss Planner') }}</span>
            </a>

            <a href="{{ route('statics.treasury') }}"
               class="w-full flex items-center gap-3 px-4 py-2.5 group transition-all {{ request()->routeIs('statics.treasury') ? 'bg-[#262528] text-white border-l-4 border-yellow-500' : 'text-gray-400 hover:text-gray-300 hover:bg-[#1f1f22] hover:translate-x-1' }}">
                <span
                    class="material-symbols-outlined {{ request()->routeIs('statics.treasury') ? 'text-yellow-500' : 'group-hover:text-yellow-500 transition-colors' }}">payments</span>
                <span class="font-nav text-xs font-bold uppercase tracking-widest">{{ __('Treasury') }}</span>
            </a>

            <a href="{{ route('statics.logs.index') }}"
               class="w-full flex items-center gap-3 px-4 py-2.5 group transition-all {{ request()->routeIs('statics.logs.*') ? 'bg-[#262528] text-white border-l-4 border-indigo-400' : 'text-gray-400 hover:text-gray-300 hover:bg-[#1f1f22] hover:translate-x-1' }}">
                <span
                    class="material-symbols-outlined {{ request()->routeIs('statics.logs.*') ? 'text-indigo-400' : 'group-hover:text-indigo-400 transition-colors' }}">terminal</span>
                <span class="font-nav text-xs font-bold uppercase tracking-widest">{{ __('Intelligence') }}</span>
            </a>

            <a href="{{ route('statics.gear') }}"
               class="w-full flex items-center gap-3 px-4 py-2.5 group transition-all {{ request()->routeIs('statics.gear') ? 'bg-[#262528] text-white border-l-4 border-rose-400' : 'text-gray-400 hover:text-gray-300 hover:bg-[#1f1f22] hover:translate-x-1' }}">
                <span
                    class="material-symbols-outlined {{ request()->routeIs('statics.gear') ? 'text-rose-400' : 'group-hover:text-rose-400 transition-colors' }}">shield</span>
                <span class="font-nav text-xs font-bold uppercase tracking-widest">{{ __('Gear Management') }}</span>
            </a>

            <div class="pt-4 pb-2 px-4">
                <div class="h-px bg-white/5 w-full"></div>
            </div>

            <a href="{{ route('characters.index') }}"
               class="w-full flex items-center gap-3 px-4 py-2.5 group transition-all {{ request()->routeIs('characters.*') ? 'bg-[#262528] text-white border-l-4 border-teal-400' : 'text-gray-400 hover:text-gray-300 hover:bg-[#1f1f22] hover:translate-x-1' }}">
                <span
                    class="material-symbols-outlined {{ request()->routeIs('characters.*') ? 'text-teal-400' : 'group-hover:text-teal-400 transition-colors' }}">person</span>
                <span class="font-nav text-xs font-bold uppercase tracking-widest">{{ __('My Characters') }}</span>
            </a>

            <a href="{{ route('statics.settings.profile') }}"
               class="w-full flex items-center gap-3 px-4 py-2.5 group transition-all {{ request()->routeIs('statics.settings.*') ? 'bg-[#262528] text-white border-l-4 border-slate-400' : 'text-gray-400 hover:text-gray-300 hover:bg-[#1f1f22] hover:translate-x-1' }}">
                <span
                    class="material-symbols-outlined {{ request()->routeIs('statics.settings.*') ? 'text-slate-400' : 'group-hover:text-slate-400 transition-colors' }}">settings</span>
                <span class="font-nav text-xs font-bold uppercase tracking-widest">{{ __('Settings') }}</span>
                @auth
                    @unless(Auth::user()->discord_id)
                        <span class="ml-auto w-2.5 h-2.5 bg-red-500 rounded-full animate-pulse-dot shadow-[0_0_8px_rgba(239,68,68,0.6)] shrink-0"></span>
                    @endunless
                @endauth
            </a>
        </div>
    @else
        <div class="px-6 mb-8 flex items-center gap-3">
            <img src="/images/logo.svg" alt="BlastR Logo"
                 class="h-6 w-auto drop-shadow-[0_0_8px_rgba(58,223,250,0.5)]"/>
            <div class="text-lg font-black uppercase tracking-tighter italic leading-none">
                <span class="text-white">Blast</span><span
                    class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-cyan-400">R<span
                        class="text-5xs opacity-70 ml-0.5">r<span class="text-5xs opacity-50 ml-0.5">r</span></span></span>
            </div>
        </div>
    @endif

    <div class="px-6 py-4 border-t border-white/5 flex items-center justify-center gap-3">
        <a href="{{ route('feedback.index') }}"
           title="{{ __('Feedback & Roadmap') }}"
           aria-label="{{ __('Feedback & Roadmap') }}"
           class="flex items-center justify-center h-10 w-10 rounded-lg bg-white/5 border border-white/10 text-gray-400 hover:text-cyan-400 hover:border-cyan-400/50 hover:bg-cyan-400/5 transition-all active:scale-95">
            <span class="material-symbols-outlined text-lg">forum</span>
        </a>
        <a href="https://discord.gg/rHcj6M5SEv"
           target="_blank"
           rel="noopener noreferrer"
           title="{{ __('Join our Discord') }}"
           aria-label="{{ __('Join our Discord') }}"
           class="flex items-center justify-center h-10 w-10 rounded-lg bg-white/5 border border-white/10 text-gray-400 hover:text-indigo-400 hover:border-indigo-400/50 hover:bg-indigo-400/5 transition-all active:scale-95">
            <svg viewBox="0 0 24 24" class="h-5 w-5 fill-current">
                <path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037 19.736 19.736 0 0 0-4.885 1.515.069.069 0 0 0-.032.027C.533 9.048-.32 13.572.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.927 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/>
            </svg>
        </a>
    </div>
</aside>

<!-- Main Content Canvas -->
<main class="{{ ($isOnboarding || $isBare) ? '' : 'lg:ml-64' }} pt-24 px-8 min-h-screen" id="app">
    <div class="max-w-8xl mx-auto">
        @if($isBare && Auth::check() && !$canReturnToBlastr)
            <div id="blastr-onboarding-banner"
                 class="hidden w-full lg:w-[85%] mx-auto mb-6 items-center gap-4 p-4 rounded-2xl bg-primary/10 border border-primary/30 backdrop-blur-sm">
                <span class="material-symbols-outlined text-2xl text-primary shrink-0">auto_awesome</span>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-semibold text-on-surface">
                        {{ __('Welcome to Blastr!') }} 👋
                    </div>
                    <div class="text-xs text-on-surface-variant mt-1">
                        {{ __('You can leave feedback without a full setup, but create or join a static to unlock rosters, raid planner, AI log analysis and more.') }}
                    </div>
                </div>
                <a href="{{ route('onboarding.index') }}"
                   class="px-4 py-2 rounded-lg text-3xs font-semibold uppercase tracking-wider bg-primary text-on-primary hover:bg-primary/90 active:scale-95 transition shrink-0">
                    {{ __('Complete setup') }}
                </a>
                <button type="button"
                        onclick="dismissBlastrOnboardingBanner()"
                        class="p-1 rounded hover:bg-white/10 text-on-surface-variant hover:text-on-surface transition shrink-0">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>
            <script>
                (function () {
                    if (!localStorage.getItem('alert_dismissed_blastr_onboarding')) {
                        const el = document.getElementById('blastr-onboarding-banner');
                        if (el) {
                            el.classList.remove('hidden');
                            el.classList.add('flex');
                        }
                    }
                })();
                function dismissBlastrOnboardingBanner() {
                    localStorage.setItem('alert_dismissed_blastr_onboarding', '1');
                    const el = document.getElementById('blastr-onboarding-banner');
                    if (el) { el.classList.add('hidden'); el.classList.remove('flex'); }
                }
            </script>
        @endif
        {{ $slot }}
    </div>
</main>
</body>
</html>
