<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
    </div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <div>
            <label for="password" class="block font-headline text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-2 ml-1">{{ __('Password') }}</label>
            <input id="password" class="bg-surface-container-lowest border-none focus:ring-0 text-white font-bold border-b-2 border-transparent focus:border-primary transition-all p-3 rounded-sm block mt-1 w-full" type="password" name="password" required autocomplete="current-password" />
            @if ($errors->get('password'))
                <ul class="text-sm text-red-600 space-y-1 mt-2">
                    @foreach ($errors->get('password') as $message)<li>{{ $message }}</li>@endforeach
                </ul>
            @endif
        </div>

        <div class="flex justify-end mt-4">
            <button type="submit" class="inline-flex items-center px-6 py-3 bg-primary text-on-primary font-headline font-bold text-xs uppercase tracking-widest rounded-sm hover:brightness-110 active:scale-95 transition-all shadow-[0_0_20px_rgba(255,255,255,0.05)]">
                {{ __('Confirm') }}
            </button>
        </div>
    </form>
</x-guest-layout>
