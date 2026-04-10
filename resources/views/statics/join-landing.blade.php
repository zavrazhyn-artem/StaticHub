<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ __('Join Team') }} — {{ $staticName }} | BlastR</title>
        <link rel="icon" href="/images/logo.svg" type="image/svg+xml">

        <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;700;900&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-background text-on-background min-h-screen arcane-bg antialiased flex flex-col items-center justify-center p-6 overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-full overflow-hidden pointer-events-none z-0">
            <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-primary/10 rounded-full blur-[120px]"></div>
            <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-cyan-500/10 rounded-full blur-[120px]"></div>
        </div>

        <div class="relative z-10 text-center max-w-lg w-full space-y-8">
            {{-- Logo --}}
            <div class="mb-2 flex items-center justify-center gap-4 drop-shadow-[0_0_40px_rgba(58,223,250,0.3)]">
                <img src="/images/logo.svg" alt="BlastR Logo" class="w-16 h-16 object-contain" />
                <div class="text-4xl font-black uppercase tracking-tighter italic leading-none pr-2">
                    <span class="text-white">Blast</span><span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-cyan-400">R</span>
                </div>
            </div>

            {{-- Invite card --}}
            <div class="bg-surface-container-low border border-white/10 rounded-2xl p-8 backdrop-blur-md space-y-6">
                <div class="space-y-2">
                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary/10 border border-primary/20 text-primary text-[10px] font-bold uppercase tracking-[0.2em]">
                        <span class="material-symbols-outlined text-sm">mail</span>
                        {{ __('Team Invitation') }}
                    </span>
                    <h1 class="text-2xl font-headline font-black text-white uppercase tracking-wide mt-4">
                        {{ $staticName }}
                    </h1>
                    <p class="text-sm text-on-surface-variant">
                        {{ __('You have been invited to join this team') }}
                    </p>
                </div>

                {{-- Team info grid --}}
                <div class="grid grid-cols-2 gap-4 text-left">
                    {{-- Region --}}
                    <div class="bg-white/5 rounded-xl p-4 border border-white/5">
                        <div class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-1">{{ __('Region') }}</div>
                        <div class="text-white font-headline font-bold text-sm flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary text-base">public</span>
                            {{ $region }}
                        </div>
                    </div>

                    {{-- Members --}}
                    <div class="bg-white/5 rounded-xl p-4 border border-white/5">
                        <div class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-1">{{ __('Members') }}</div>
                        <div class="text-white font-headline font-bold text-sm flex items-center gap-2">
                            <span class="material-symbols-outlined text-cyan-400 text-base">groups</span>
                            {{ $memberCount }}
                        </div>
                    </div>

                    {{-- Owner --}}
                    <div class="bg-white/5 rounded-xl p-4 border border-white/5 col-span-2">
                        <div class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-1">{{ __('Invited by') }}</div>
                        <div class="text-white font-headline font-bold text-sm flex items-center gap-3">
                            @if($ownerAvatar)
                                <img src="{{ $ownerAvatar }}" alt="" class="w-8 h-8 rounded-full border border-white/10">
                            @else
                                <span class="material-symbols-outlined text-success-neon text-base">person</span>
                            @endif
                            {{ $ownerName }}
                        </div>
                    </div>

                    {{-- Raid schedule (if configured) --}}
                    @if(!empty($raidDays))
                        @php
                            $dayLabels = [
                                'mon' => __('Mon'), 'tue' => __('Tue'), 'wed' => __('Wed'),
                                'thu' => __('Thu'), 'fri' => __('Fri'), 'sat' => __('Sat'), 'sun' => __('Sun'),
                            ];
                        @endphp
                        <div class="bg-white/5 rounded-xl p-4 border border-white/5 col-span-2">
                            <div class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-2">{{ __('Raid Days') }}</div>
                            <div class="flex flex-wrap gap-2">
                                @foreach($raidDays as $day)
                                    <span class="px-2 py-1 bg-primary/10 border border-primary/20 rounded text-primary text-xs font-bold uppercase">
                                        {{ $dayLabels[$day] ?? $day }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Auth button --}}
                <div class="pt-2">
                    <a href="{{ url('/auth/battlenet/redirect') }}"
                       class="group relative w-full inline-flex items-center justify-center gap-3 px-8 py-4 bg-[#00aeff] text-white font-headline font-black text-sm uppercase tracking-[0.2em] rounded-sm transition-all hover:scale-105 active:scale-95 shadow-[0_0_40px_rgba(0,174,255,0.3)]">
                        <span class="material-symbols-outlined text-xl">login</span>
                        {{ __('Authorize Battle.net') }}
                    </a>
                    <p class="text-[10px] text-on-surface-variant mt-3 uppercase tracking-wider">
                        {{ __('Sign in to select your character and join the team') }}
                    </p>
                </div>
            </div>
        </div>

        <footer class="fixed bottom-8 left-0 w-full text-center z-10">
            <p class="text-[9px] font-bold text-gray-600 uppercase tracking-[0.5em]">&copy; {{ date('Y') }} BLASTR_SYSTEMS // PROTOCOL_V1</p>
        </footer>
    </body>
</html>
