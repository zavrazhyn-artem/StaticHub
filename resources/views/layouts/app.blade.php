@php use App\Policies\StaticGroupPolicy; @endphp
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
    <link
        href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Manrope:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
          rel="stylesheet"/>

    <script>
        // Читаємо JSON з перекладами і записуємо в глобальну змінну вікна
        window.translations = {!! file_exists(base_path('lang/' . app()->getLocale() . '.json'))
            ? file_get_contents(base_path('lang/' . app()->getLocale() . '.json'))
            : '{}' !!};
    </script>
    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Wowhead Tooltips -->
    <script>const whTooltips = {colorLinks: true, iconizeLinks: false, renameLinks: false};</script>
    <script src="https://wow.zamimg.com/js/tooltips.js"></script>
</head>
<body class="bg-background text-on-background min-h-screen arcane-bg antialiased">
@php
    $static = Auth::user() ? Auth::user()->statics->first() : null;
    $isOnboarding = $onboarding ?? false;
@endphp
    <!-- TopNavBar -->
<header
    class="fixed top-0 z-50 w-full flex justify-between items-center px-6 py-4 bg-[#0e0e10]/80 backdrop-blur-xl shadow-[0_20px_40px_rgba(0,0,0,0.4)]">
    <div class="flex items-center gap-8">
        <div class="flex items-center gap-3">
            <img src="/images/logo.svg" alt="BlastR Logo"
                 class="h-8 w-auto drop-shadow-[0_0_8px_rgba(58,223,250,0.5)]"/>
            <div class="text-2xl font-black uppercase tracking-tighter italic leading-none">
                <span class="text-white">Blast</span><span
                    class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-cyan-400">R<span
                        class="text-xs opacity-70 ml-0.5">r<span
                            class="text-[10px] opacity-50 ml-0.5">r</span></span></span>
            </div>
        </div>
        @if($static && !$isOnboarding)
            @can('manage', $static)
            <div class="hidden md:flex items-center gap-4 ml-4">
                <button onclick="handleInviteClick({{ $static->id }})"
                        class="flex items-center gap-2 px-4 py-1.5 bg-cyan-500/10 border border-cyan-500/50 text-cyan-400 rounded-md hover:bg-cyan-500 hover:text-white transition-all active:scale-95 group">
                    <span class="material-symbols-outlined text-sm">person_add</span>
                    <span
                        class="font-headline text-[10px] font-bold uppercase tracking-widest">{{ __("Invite to Group") }}</span>
                </button>
                <div id="invite-toast"
                     class="hidden fixed bottom-6 right-6 bg-cyan-600 text-white px-6 py-3 rounded-lg shadow-2xl animate-bounce font-headline text-xs font-bold uppercase tracking-widest z-[100]">
                    Invite Link Copied!
                </div>
            </div>
            @endcan

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
                                class="flex items-center gap-3 w-full px-4 py-2 text-start font-headline text-[10px] font-bold uppercase tracking-widest text-gray-400 hover:text-cyan-400 hover:bg-white/5 transition-colors {{ $currentLocale === $locale ? 'text-cyan-400 bg-white/5' : '' }}">
                            <img src="/images/flags/{{ $info['country'] }}.svg" alt="{{ $info['country'] }}"
                                 class="w-5 h-auto rounded-sm">
                            {{ $info['label'] }}
                        </button>
                    @endforeach
                </form>
            </div>
        </div>

        @unless($isOnboarding)
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
                        <div class="text-[10px] text-gray-500 font-medium truncate">{{ Auth::user()->email }}</div>
                    </div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <a href="{{ route('logout') }}"
                           onclick="event.preventDefault(); this.closest('form').submit();"
                           class="block w-full px-4 py-2 text-start font-headline text-[10px] font-bold uppercase tracking-widest text-gray-400 hover:text-red-400 hover:bg-white/5 transition-colors">
                            {{ __('Log Out') }}
                        </a>
                    </form>
                </div>
            </div>
        </div>
        @endunless
    </div>
</header>

<!-- SideNavBar -->
<aside class="fixed left-0 top-0 h-screen w-64 bg-[#131315] border-r border-white/5 pt-24 hidden {{ $isOnboarding ? '' : 'lg:flex' }} flex-col">
    @if($static)
        <div class="px-6 mb-8">
            <h2 class="font-headline text-lg font-bold text-white uppercase tracking-tighter">{{ $static->name }}</h2>
            <p class="text-xs font-bold text-cyan-400 uppercase tracking-widest mt-1">Mythic Progression</p>
        </div>

        <div class="flex-1 overflow-y-auto px-3 space-y-1">
            <a href="{{ route('statics.dashboard', $static->id) }}"
               class="w-full flex items-center gap-3 px-4 py-2.5 group transition-all {{ request()->routeIs('statics.dashboard') ? 'bg-[#262528] text-white border-l-4 border-cyan-400' : 'text-gray-500 hover:text-gray-300 hover:bg-[#1f1f22] hover:translate-x-1' }}">
                <span
                    class="material-symbols-outlined {{ request()->routeIs('statics.dashboard') ? 'text-cyan-400' : 'group-hover:text-cyan-400 transition-colors' }}">dashboard</span>
                <span class="font-headline text-xs font-bold uppercase tracking-widest">{{ __('Dashboard') }}</span>
            </a>

            <a href="{{ route('statics.roster', $static->id) }}"
               class="w-full flex items-center gap-3 px-4 py-2.5 group transition-all {{ request()->routeIs('statics.roster') ? 'bg-[#262528] text-white border-l-4 border-cyan-400' : 'text-gray-500 hover:text-gray-300 hover:bg-[#1f1f22] hover:translate-x-1' }}">
                <span
                    class="material-symbols-outlined {{ request()->routeIs('statics.roster') ? 'text-cyan-400' : 'group-hover:text-cyan-400 transition-colors' }}">groups</span>
                <span class="font-headline text-xs font-bold uppercase tracking-widest">{{ __('Roster') }}</span>
            </a>


            <a href="{{ route('schedule.index') }}"
               class="w-full flex items-center gap-3 px-4 py-2.5 group transition-all {{ request()->routeIs('schedule.*') ? 'bg-[#262528] text-white border-l-4 border-cyan-400' : 'text-gray-500 hover:text-gray-300 hover:bg-[#1f1f22] hover:translate-x-1' }}">
                <span
                    class="material-symbols-outlined {{ request()->routeIs('schedule.*') ? 'text-cyan-400' : 'group-hover:text-cyan-400 transition-colors' }}">calendar_month</span>
                <span class="font-headline text-xs font-bold uppercase tracking-widest">{{ __('Schedule') }}</span>
            </a>

            <a href="{{ route('statics.treasury', $static->id) }}"
               class="w-full flex items-center gap-3 px-4 py-2.5 group transition-all {{ request()->routeIs('statics.treasury') ? 'bg-[#262528] text-white border-l-4 border-cyan-400' : 'text-gray-500 hover:text-gray-300 hover:bg-[#1f1f22] hover:translate-x-1' }}">
                <span
                    class="material-symbols-outlined {{ request()->routeIs('statics.treasury') ? 'text-cyan-400' : 'group-hover:text-cyan-400 transition-colors' }}">payments</span>
                <span class="font-headline text-xs font-bold uppercase tracking-widest">{{ __('Treasury') }}</span>
            </a>

            <a href="{{ route('statics.logs.index', $static->id) }}"
               class="w-full flex items-center gap-3 px-4 py-2.5 group transition-all {{ request()->routeIs('statics.logs.*') ? 'bg-[#262528] text-white border-l-4 border-amber-500' : 'text-gray-500 hover:text-gray-300 hover:bg-[#1f1f22] hover:translate-x-1' }}">
                <span
                    class="material-symbols-outlined {{ request()->routeIs('statics.logs.*') ? 'text-amber-500' : 'group-hover:text-amber-500 transition-colors' }}">terminal</span>
                <span class="font-headline text-xs font-bold uppercase tracking-widest">{{ __('Intelligence') }}</span>
            </a>

            <div class="pt-4 pb-2 px-4">
                <div class="h-px bg-white/5 w-full"></div>
            </div>

            <a href="{{ route('characters.index') }}"
               class="w-full flex items-center gap-3 px-4 py-2.5 group transition-all {{ request()->routeIs('characters.*') ? 'bg-[#262528] text-white border-l-4 border-cyan-400' : 'text-gray-500 hover:text-gray-300 hover:bg-[#1f1f22] hover:translate-x-1' }}">
                <span
                    class="material-symbols-outlined {{ request()->routeIs('characters.*') ? 'text-cyan-400' : 'group-hover:text-cyan-400 transition-colors' }}">person</span>
                <span class="font-headline text-xs font-bold uppercase tracking-widest">{{ __('My Characters') }}</span>
            </a>

            <a href="{{ route('statics.settings.profile', $static->id) }}"
               class="w-full flex items-center gap-3 px-4 py-2.5 group transition-all {{ request()->routeIs('statics.settings.*') ? 'bg-[#262528] text-white border-l-4 border-cyan-400' : 'text-gray-500 hover:text-gray-300 hover:bg-[#1f1f22] hover:translate-x-1' }}">
                <span
                    class="material-symbols-outlined {{ request()->routeIs('statics.settings.*') ? 'text-cyan-400' : 'group-hover:text-cyan-400 transition-colors' }}">settings</span>
                <span class="font-headline text-xs font-bold uppercase tracking-widest">{{ __('Settings') }}</span>
            </a>
        </div>
    @else
        <div class="px-6 mb-8 flex items-center gap-3">
            <img src="/images/logo.svg" alt="BlastR Logo"
                 class="h-6 w-auto drop-shadow-[0_0_8px_rgba(58,223,250,0.5)]"/>
            <div class="text-lg font-black uppercase tracking-tighter italic leading-none">
                <span class="text-white">Blast</span><span
                    class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-cyan-400">R<span
                        class="text-[8px] opacity-70 ml-0.5">r<span class="text-[6px] opacity-50 ml-0.5">r</span></span></span>
            </div>
        </div>
    @endif

    <div class="p-6 border-t border-white/5 space-y-4">

    </div>
</aside>

<!-- Main Content Canvas -->
<main class="{{ $isOnboarding ? '' : 'lg:ml-64' }} pt-24 px-8 min-h-screen" id="app">
    <div class="max-w-7xl mx-auto">
        {{ $slot }}
    </div>
</main>
</body>
</html>
