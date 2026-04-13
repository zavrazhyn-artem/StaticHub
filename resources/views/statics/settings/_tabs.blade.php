<div class="flex items-center gap-2 border-b border-white/5 mb-8">
    <a href="{{ route('statics.settings.profile', $static) }}"
       class="px-6 py-4 font-headline text-xs font-bold uppercase tracking-widest transition-all relative group {{ request()->routeIs('statics.settings.profile') ? 'text-slate-400' : 'text-on-surface-variant hover:text-white' }}">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">person</span>
            {{ __('Profile') }}
        </div>
        @if(request()->routeIs('statics.settings.profile'))
            <div class="absolute bottom-0 left-0 w-full h-0.5 bg-slate-400 shadow-[0_0_10px_rgba(148,163,184,0.5)]"></div>
        @endif
    </a>
    @can('manage', $static)
    <a href="{{ route('statics.settings.schedule', $static) }}"
       class="px-6 py-4 font-headline text-xs font-bold uppercase tracking-widest transition-all relative group {{ request()->routeIs('statics.settings.schedule') ? 'text-slate-400' : 'text-on-surface-variant hover:text-white' }}">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">calendar_month</span>
            {{ __('Schedule & Treasury') }}
        </div>
        @if(request()->routeIs('statics.settings.schedule'))
            <div class="absolute bottom-0 left-0 w-full h-0.5 bg-slate-400 shadow-[0_0_10px_rgba(148,163,184,0.5)]"></div>
        @endif
    </a>
    <a href="{{ route('statics.settings.discord', $static) }}"
       class="px-6 py-4 font-headline text-xs font-bold uppercase tracking-widest transition-all relative group {{ request()->routeIs('statics.settings.discord') ? 'text-[#5865F2]' : 'text-on-surface-variant hover:text-white' }}">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">forum</span>
            {{ __('Discord') }}
        </div>
        @if(request()->routeIs('statics.settings.discord'))
            <div class="absolute bottom-0 left-0 w-full h-0.5 bg-[#5865F2] shadow-[0_0_10px_rgba(88,101,242,0.5)]"></div>
        @endif
    </a>
    <a href="{{ route('statics.settings.logs', $static) }}"
       class="px-6 py-4 font-headline text-xs font-bold uppercase tracking-widest transition-all relative group {{ request()->routeIs('statics.settings.logs') ? 'text-slate-400' : 'text-on-surface-variant hover:text-white' }}">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">analytics</span>
            {{ __('Warcraft Logs & AI') }}
        </div>
        @if(request()->routeIs('statics.settings.logs'))
            <div class="absolute bottom-0 left-0 w-full h-0.5 bg-slate-400 shadow-[0_0_10px_rgba(148,163,184,0.5)]"></div>
        @endif
    </a>
    @endcan
</div>
