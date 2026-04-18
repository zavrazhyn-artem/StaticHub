<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>BlastR - Blast Your Raid</title>
        <link rel="icon" href="/images/logo.svg" type="image/svg+xml">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;700;900&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>

        <script>
            window.translations = {!! file_exists(base_path('lang/' . app()->getLocale() . '.json'))
                ? file_get_contents(base_path('lang/' . app()->getLocale() . '.json'))
                : '{}' !!};
        </script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-background text-on-background subpixel-antialiased">
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
        @endphp
        <div class="fixed top-6 right-6 z-50 group">
            <button class="flex items-center gap-2 px-3 py-1.5 bg-white/5 border border-white/10 rounded-md hover:bg-white/10 transition-all text-xs font-bold uppercase tracking-widest text-gray-400 hover:text-white">
                <img src="/images/flags/{{ $localeMap[$currentLocale]['country'] ?? 'GB' }}.svg" alt="{{ $currentLocale }}" class="w-5 h-auto rounded-sm">
                <span class="material-symbols-outlined text-sm">expand_more</span>
            </button>
            <div class="absolute right-0 mt-2 w-36 py-1 bg-surface-container-highest border border-white/10 shadow-2xl rounded-md opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50">
                <form action="{{ route('language.switch') }}" method="POST">
                    @csrf
                    @foreach($availableLocales as $locale)
                        @php $info = $localeMap[$locale]; @endphp
                        <button name="locale" value="{{ $locale }}" class="flex items-center gap-3 w-full px-4 py-2 text-start text-3xs font-semibold uppercase tracking-wider text-gray-400 hover:text-cyan-400 hover:bg-white/5 transition-colors {{ $currentLocale === $locale ? 'text-cyan-400 bg-white/5' : '' }}">
                            <img src="/images/flags/{{ $info['country'] }}.svg" alt="{{ $info['country'] }}" class="w-5 h-auto rounded-sm">
                            {{ $info['label'] }}
                        </button>
                    @endforeach
                </form>
            </div>
        </div>

        <div id="app">
            <landing-page
                :is-authenticated="{{ auth()->check() ? 'true' : 'false' }}"
                dashboard-url="{{ route('dashboard') }}"
                login-url="{{ url('/auth/battlenet/redirect') }}"
            ></landing-page>
        </div>
    </body>
</html>
