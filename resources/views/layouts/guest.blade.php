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
    <body class="bg-background text-on-background min-h-screen arcane-bg antialiased flex flex-col items-center justify-center p-6">
        <div class="w-full sm:max-w-md mt-6 px-8 py-10 bg-surface-container-low border border-white/5 shadow-2xl overflow-hidden rounded-xl backdrop-blur-md">
            <div class="flex justify-center mb-8">
                <a href="/" class="text-2xl font-black text-primary uppercase tracking-[0.2em] font-headline italic">
                    Static <span class="text-white">Hub</span>
                </a>
            </div>
            {{ $slot }}
        </div>
    </body>
</html>
