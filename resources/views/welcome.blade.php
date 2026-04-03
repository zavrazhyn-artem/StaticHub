<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>BlastR - Blast Your Raid</title>
        <link rel="icon" href="/images/logo.svg" type="image/svg+xml">

        <!-- Fonts -->
        <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;700;900&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-background text-on-background min-h-screen arcane-bg antialiased flex flex-col items-center justify-center p-6 overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-full overflow-hidden pointer-events-none z-0">
            <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-primary/10 rounded-full blur-[120px]"></div>
            <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-cyan-500/10 rounded-full blur-[120px]"></div>
        </div>

        <div class="relative z-10 text-center max-w-4xl w-full space-y-12">
            <div class="space-y-4">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/5 border border-white/10 backdrop-blur-md mb-4">
                    <span class="w-2 h-2 bg-success-neon rounded-full animate-pulse"></span>
                    <span class="text-[10px] font-bold uppercase tracking-[0.3em] text-on-surface-variant">System Online: Midnight S1</span>
                </div>
                <div class="mb-8 flex items-center justify-center gap-6 drop-shadow-[0_0_40px_rgba(58,223,250,0.3)] transition-transform hover:scale-105 duration-500 mx-auto">
                    <img src="/images/logo.svg" alt="BlastR Logo" class="w-32 h-32 object-contain" />
                    <div class="text-6xl md:text-8xl font-black uppercase tracking-tighter italic leading-none pr-4">
                        <span class="text-white">Blast</span><span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-cyan-400">R</span>
                    </div>
                </div>
                <p class="mt-4 font-slogan text-lg md:text-xl font-bold tracking-[0.4em] uppercase text-on-surface drop-shadow-[0_0_8px_rgba(58,223,250,0.4)]">
                    Blast Your Raid
                </p>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-6 pt-8">
                @auth
                    <a href="{{ route('dashboard') }}" class="group relative px-12 py-4 bg-primary text-on-primary font-headline font-black text-sm uppercase tracking-[0.2em] rounded-sm transition-all hover:scale-105 active:scale-95 shadow-[0_0_40px_rgba(255,255,255,0.1)]">
                        <span class="relative z-10 flex items-center gap-3">
                            Enter Command Center
                            <span class="material-symbols-outlined transition-transform group-hover:translate-x-1">arrow_forward</span>
                        </span>
                    </a>
                @else
                    <a href="{{ url('/auth/battlenet/redirect') }}" class="group relative px-12 py-4 bg-[#00aeff] text-white font-headline font-black text-sm uppercase tracking-[0.2em] rounded-sm transition-all hover:scale-105 active:scale-95 shadow-[0_0_40px_rgba(0,174,255,0.3)]">
                        <span class="relative z-10 flex items-center gap-3">
                            <span class="material-symbols-outlined text-xl">login</span>
                            Authorize Battle.net
                        </span>
                    </a>
                @endauth
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-24 px-4">
                <div class="bg-surface-container-low p-6 rounded-xl border border-white/5 backdrop-blur-md">
                    <span class="material-symbols-outlined text-primary text-3xl mb-4">groups</span>
                    <h3 class="font-headline font-bold text-white uppercase tracking-widest text-xs mb-2">Roster Tactical</h3>
                    <p class="text-[10px] text-on-surface-variant leading-relaxed uppercase tracking-wider">Complete visibility of your raid force, mains and alts synchronized via API.</p>
                </div>
                <div class="bg-surface-container-low p-6 rounded-xl border border-white/5 backdrop-blur-md">
                    <span class="material-symbols-outlined text-cyan-400 text-3xl mb-4">inventory_2</span>
                    <h3 class="font-headline font-bold text-white uppercase tracking-widest text-xs mb-2">Logistics Hub</h3>
                    <p class="text-[10px] text-on-surface-variant leading-relaxed uppercase tracking-wider">Live AH price tracking for raid consumables and cost-efficiency calculators.</p>
                </div>
                <div class="bg-surface-container-low p-6 rounded-xl border border-white/5 backdrop-blur-md">
                    <span class="material-symbols-outlined text-success-neon text-3xl mb-4">payments</span>
                    <h3 class="font-headline font-bold text-white uppercase tracking-widest text-xs mb-2">Guild Ledger</h3>
                    <p class="text-[10px] text-on-surface-variant leading-relaxed uppercase tracking-wider">Transparent treasury management and automated guild tax calculation systems.</p>
                </div>
            </div>
        </div>

        <footer class="fixed bottom-8 left-0 w-full text-center z-10">
            <p class="text-[9px] font-bold text-gray-600 uppercase tracking-[0.5em]">&copy; {{ date('Y') }} BLASTR_SYSTEMS // PROTOCOL_V1</p>
        </footer>
    </body>
</html>
