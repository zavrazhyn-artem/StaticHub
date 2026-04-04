<x-guest-layout>
    <form method="POST" action="{{ route('password.store') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <label for="email" class="block font-headline text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-2 ml-1">{{ __('Email') }}</label>
            <input id="email" class="bg-surface-container-lowest border-none focus:ring-0 text-white font-bold border-b-2 border-transparent focus:border-primary transition-all p-3 rounded-sm block mt-1 w-full" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username" />
            @if ($errors->get('email'))
                <ul class="text-sm text-red-600 space-y-1 mt-2">
                    @foreach ($errors->get('email') as $message)<li>{{ $message }}</li>@endforeach
                </ul>
            @endif
        </div>

        <div class="mt-4">
            <label for="password" class="block font-headline text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-2 ml-1">{{ __('Password') }}</label>
            <input id="password" class="bg-surface-container-lowest border-none focus:ring-0 text-white font-bold border-b-2 border-transparent focus:border-primary transition-all p-3 rounded-sm block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            @if ($errors->get('password'))
                <ul class="text-sm text-red-600 space-y-1 mt-2">
                    @foreach ($errors->get('password') as $message)<li>{{ $message }}</li>@endforeach
                </ul>
            @endif
        </div>

        <div class="mt-4">
            <label for="password_confirmation" class="block font-headline text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-2 ml-1">{{ __('Confirm Password') }}</label>
            <input id="password_confirmation" class="bg-surface-container-lowest border-none focus:ring-0 text-white font-bold border-b-2 border-transparent focus:border-primary transition-all p-3 rounded-sm block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <button type="submit" class="inline-flex items-center px-6 py-3 bg-primary text-on-primary font-headline font-bold text-xs uppercase tracking-widest rounded-sm hover:brightness-110 active:scale-95 transition-all shadow-[0_0_20px_rgba(255,255,255,0.05)]">
                {{ __('Reset Password') }}
            </button>
        </div>
    </form>
</x-guest-layout>
