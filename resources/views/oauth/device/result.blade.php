<x-guest-layout>
    <div class="text-center space-y-5">
        @if ($kind === 'approved')
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-success-neon/10 border border-success-neon/40">
                <span class="material-symbols-outlined text-success-neon text-3xl">check_circle</span>
            </div>
            <h1 class="font-headline text-lg font-black uppercase tracking-widest text-white">
                {{ __('Authorized') }}
            </h1>
        @else
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-on-surface-variant/10 border border-on-surface-variant/30">
                <span class="material-symbols-outlined text-on-surface-variant text-3xl">block</span>
            </div>
            <h1 class="font-headline text-lg font-black uppercase tracking-widest text-white">
                {{ __('Denied') }}
            </h1>
        @endif

        <p class="text-sm text-on-surface-variant">{{ $message }}</p>
    </div>
</x-guest-layout>
