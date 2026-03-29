<div class="flex items-center gap-2 border-b border-white/5 mb-8">
    <a href="{{ route('statics.settings.schedule', $static) }}"
       class="px-6 py-4 font-headline text-xs font-bold uppercase tracking-widest transition-all relative group {{ request()->routeIs('statics.settings.schedule') ? 'text-primary' : 'text-on-surface-variant hover:text-white' }}">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">calendar_month</span>
            Schedule & Treasury
        </div>
        @if(request()->routeIs('statics.settings.schedule'))
            <div class="absolute bottom-0 left-0 w-full h-0.5 bg-primary shadow-[0_0_10px_rgba(34,211,238,0.5)]"></div>
        @endif
    </a>
    <a href="{{ route('statics.settings.logs', $static) }}"
       class="px-6 py-4 font-headline text-xs font-bold uppercase tracking-widest transition-all relative group {{ request()->routeIs('statics.settings.logs') ? 'text-primary' : 'text-on-surface-variant hover:text-white' }}">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">analytics</span>
            Warcraft Logs & AI
        </div>
        @if(request()->routeIs('statics.settings.logs'))
            <div class="absolute bottom-0 left-0 w-full h-0.5 bg-primary shadow-[0_0_10px_rgba(34,211,238,0.5)]"></div>
        @endif
    </a>
</div>
