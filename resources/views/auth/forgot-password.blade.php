<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
    </div>

    @if (session('status'))
        <div class="mb-4 font-medium text-sm text-green-600">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div>
            <label for="email" class="block font-headline text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-2 ml-1">{{ __('Email') }}</label>
            <input id="email" class="bg-surface-container-lowest border-none focus:ring-0 text-white font-bold border-b-2 border-transparent focus:border-primary transition-all p-3 rounded-sm block mt-1 w-full" type="email" name="email" value="{{ old('email') }}" required autofocus />
            @if ($errors->get('email'))
                <ul class="text-sm text-red-600 space-y-1 mt-2">
                    @foreach ($errors->get('email') as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="flex items-center justify-end mt-4">
            <button type="submit" class="inline-flex items-center px-6 py-3 bg-primary text-on-primary font-headline font-bold text-xs uppercase tracking-widest rounded-sm hover:brightness-110 active:scale-95 transition-all shadow-[0_0_20px_rgba(255,255,255,0.05)]">
                {{ __('Email Password Reset Link') }}
            </button>
        </div>
    </form>
</x-guest-layout>
