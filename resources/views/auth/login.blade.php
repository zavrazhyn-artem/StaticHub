<x-guest-layout>
    @if (session('status'))
        <div class="mb-4 font-medium text-sm text-green-600">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div>
            <label for="email" class="block font-headline text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-2 ml-1">{{ __('Email') }}</label>
            <input id="email" class="bg-surface-container-lowest border-none focus:ring-0 text-white font-bold border-b-2 border-transparent focus:border-primary transition-all p-3 rounded-sm block mt-1 w-full" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" />
            @if ($errors->get('email'))
                <ul class="text-sm text-red-600 space-y-1 mt-2">
                    @foreach ($errors->get('email') as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="mt-4">
            <label for="password" class="block font-headline text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-2 ml-1">{{ __('Password') }}</label>
            <input id="password" class="bg-surface-container-lowest border-none focus:ring-0 text-white font-bold border-b-2 border-transparent focus:border-primary transition-all p-3 rounded-sm block mt-1 w-full" type="password" name="password" required autocomplete="current-password" />
            @if ($errors->get('password'))
                <ul class="text-sm text-red-600 space-y-1 mt-2">
                    @foreach ($errors->get('password') as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <button type="submit" class="inline-flex items-center px-6 py-3 bg-primary text-on-primary font-headline font-bold text-xs uppercase tracking-widest rounded-sm hover:brightness-110 active:scale-95 transition-all shadow-[0_0_20px_rgba(255,255,255,0.05)] ms-3">
                {{ __('Log in') }}
            </button>
        </div>
    </form>
</x-guest-layout>
