<x-guest-layout>
    <h1 class="text-center font-headline text-xl font-black uppercase tracking-widest text-white mb-1">
        {{ __('Authorize BlastR Desktop') }}
    </h1>
    <p class="text-center text-sm text-on-surface-variant mb-6">
        {{ __('Confirm the code shown in your desktop app.') }}
    </p>

    @if ($code === null && $user_code === '')
        {{-- No code in URL — let the user paste / type one. --}}
        <form method="POST" action="{{ route('oauth.device.approve') }}" class="space-y-4">
            @csrf
            <label class="block">
                <span class="text-[11px] font-headline font-bold uppercase tracking-widest text-on-surface-variant">
                    {{ __('Code from BlastR Desktop') }}
                </span>
                <input
                    type="text"
                    name="user_code"
                    placeholder="ABCD-EFGH"
                    autocomplete="off"
                    autocapitalize="characters"
                    spellcheck="false"
                    class="mt-1 w-full px-4 py-3 rounded-lg bg-surface-container-high border border-white/10 focus:border-primary focus:ring-1 focus:ring-primary text-center font-mono text-lg tracking-[0.4em] uppercase text-white"
                    required
                />
                @error('user_code')
                    <p class="mt-1 text-xs text-error">{{ $message }}</p>
                @enderror
            </label>
            <button type="submit" class="w-full px-4 py-3 rounded-lg bg-primary hover:bg-primary/90 text-on-primary font-headline font-bold uppercase tracking-widest text-sm transition">
                {{ __('Continue') }}
            </button>
        </form>

    @elseif ($code === null)
        {{-- Code was supplied but doesn't exist or already consumed. --}}
        <div class="text-center space-y-4">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-error/10 border border-error/30">
                <span class="material-symbols-outlined text-error text-2xl">error</span>
            </div>
            <div>
                <p class="text-sm text-white font-medium">
                    {{ __('Code :code is not valid.', ['code' => $user_code]) }}
                </p>
                <p class="text-xs text-on-surface-variant mt-1">
                    {{ __('It may have expired or already been used. Restart the pairing in BlastR Desktop and try again.') }}
                </p>
            </div>
            <a href="{{ route('oauth.device.show') }}" class="inline-block text-xs text-primary hover:underline">
                {{ __('Enter a different code') }}
            </a>
        </div>

    @else
        {{-- Pending code, ready to approve/deny. --}}
        <div class="space-y-5">
            <div class="px-4 py-3 rounded-lg bg-surface-container-high border border-white/10 space-y-2">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-on-surface-variant uppercase tracking-widest font-headline">{{ __('Application') }}</span>
                    <span class="font-mono text-white">{{ $code->client_name }}</span>
                </div>
                <div class="flex items-center justify-between text-xs">
                    <span class="text-on-surface-variant uppercase tracking-widest font-headline">{{ __('Code') }}</span>
                    <span class="font-mono text-primary text-base tracking-[0.3em]">{{ $user_code }}</span>
                </div>
                <div class="flex items-start justify-between text-xs gap-4">
                    <span class="text-on-surface-variant uppercase tracking-widest font-headline shrink-0">{{ __('Permissions') }}</span>
                    <span class="text-right text-white text-[11px]">
                        @foreach ($code->scope as $s)
                            <span class="inline-block px-1.5 py-px rounded bg-white/5 border border-white/10 font-mono ml-1">{{ $s }}</span>
                        @endforeach
                    </span>
                </div>
            </div>

            <p class="text-center text-xs text-on-surface-variant">
                {{ __('Signed in as :name', ['name' => $authenticated_as->battletag ?? $authenticated_as->name]) }}
            </p>

            <div class="flex gap-3">
                <form method="POST" action="{{ route('oauth.device.deny') }}" class="flex-1">
                    @csrf
                    <input type="hidden" name="user_code" value="{{ $user_code_raw }}">
                    <button type="submit" class="w-full px-4 py-3 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-on-surface-variant hover:text-white font-headline font-bold uppercase tracking-widest text-sm transition">
                        {{ __('Deny') }}
                    </button>
                </form>
                <form method="POST" action="{{ route('oauth.device.approve') }}" class="flex-1">
                    @csrf
                    <input type="hidden" name="user_code" value="{{ $user_code_raw }}">
                    <button type="submit" class="w-full px-4 py-3 rounded-lg bg-primary hover:bg-primary/90 text-on-primary font-headline font-bold uppercase tracking-widest text-sm transition">
                        {{ __('Authorize') }}
                    </button>
                </form>
            </div>
        </div>
    @endif
</x-guest-layout>
