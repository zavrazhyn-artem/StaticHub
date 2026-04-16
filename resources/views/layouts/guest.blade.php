<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>
        <link rel="icon" href="/images/logo.svg" type="image/svg+xml">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;700;900&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-background text-on-background min-h-screen arcane-bg subpixel-antialiased flex flex-col items-center justify-center p-6">
        <div class="w-full sm:max-w-md mt-6 px-8 py-10 bg-surface-container-low border border-white/5 shadow-2xl overflow-hidden rounded-xl backdrop-blur-md">
            <div class="flex justify-center mb-8">
                <a href="/" class="flex items-center gap-3">
                    <img src="/images/logo.svg" alt="BlastR Logo" class="h-10 w-auto drop-shadow-[0_0_8px_rgba(58,223,250,0.5)]" />
                    <div class="text-3xl font-black uppercase tracking-tighter italic leading-none">
                        <span class="text-white">Blast</span><span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-cyan-400">R<span class="text-sm opacity-70 ml-0.5">r<span class="text-xs opacity-50 ml-0.5">r</span></span></span>
                    </div>
                </a>
            </div>
            {{ $slot }}
        </div>
    </body>
</html>
