<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>StaticHub - Manage your WoW Raid Static</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

        <!-- Styles / Scripts -->
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="bg-gray-900 text-white font-sans antialiased">
        <div class="min-h-screen flex flex-col items-center justify-center px-6 py-12">
            <div class="text-center max-w-2xl">
                <h1 class="text-5xl md:text-6xl font-bold tracking-tight mb-4">
                    Welcome to <span class="text-blue-500">StaticHub</span>
                </h1>

                <p class="text-xl text-gray-400 mb-10">
                    Manage your WoW raid static, guild taxes, and roster all in one place.
                </p>

                <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                    @auth
                        <a href="{{ route('dashboard') }}" class="inline-flex items-center px-8 py-3 bg-blue-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-lg shadow-blue-500/20">
                            Go to Dashboard
                        </a>
                    @else
                        <a href="{{ url('/auth/battlenet/redirect') }}" class="inline-flex items-center gap-3 px-8 py-3 bg-[#00aeff] border border-transparent rounded-md font-bold text-sm text-white uppercase tracking-widest hover:bg-[#008ccf] focus:outline-none focus:ring-2 focus:ring-[#00aeff] focus:ring-offset-2 transition ease-in-out duration-150 shadow-lg shadow-[#00aeff]/20">
                            <svg class="h-5 w-5 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M11.996 0C5.371 0 0 5.371 0 11.996s5.371 11.996 11.996 11.996 11.996-5.371 11.996-11.996S18.621 0 11.996 0zm3.877 15.223h-2.183l-1.354-2.825-1.354 2.825H7.798l2.257-4.148L7.798 6.927h2.183l1.354 2.825 1.354-2.825h2.183l-2.257 4.148 2.257 4.148z"/>
                            </svg>
                            Login with Battle.net
                        </a>
                    @endauth
                </div>
            </div>

            <footer class="mt-20 text-gray-500 text-sm">
                &copy; {{ date('Y') }} StaticHub. Built for Azeroth.
            </footer>
        </div>
    </body>
</html>
