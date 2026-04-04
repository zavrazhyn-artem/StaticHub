<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">{{ __('Profile Information') }}</h2>
        <p class="mt-1 text-sm text-gray-600">{{ __("Update your account's profile information and email address.") }}</p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <label for="name" class="block font-headline text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-2 ml-1">{{ __('Name') }}</label>
            <input id="name" class="bg-surface-container-lowest border-none focus:ring-0 text-white font-bold border-b-2 border-transparent focus:border-primary transition-all p-3 rounded-sm mt-1 block w-full" name="name" type="text" value="{{ old('name', $user->name) }}" required autofocus autocomplete="name" />
            @if ($errors->get('name'))
                <ul class="text-sm text-red-600 space-y-1 mt-2">
                    @foreach ($errors->get('name') as $message)<li>{{ $message }}</li>@endforeach
                </ul>
            @endif
        </div>

        <div>
            <label for="email" class="block font-headline text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-2 ml-1">{{ __('Email') }}</label>
            <input id="email" class="bg-surface-container-lowest border-none focus:ring-0 text-white font-bold border-b-2 border-transparent focus:border-primary transition-all p-3 rounded-sm mt-1 block w-full" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="username" />
            @if ($errors->get('email'))
                <ul class="text-sm text-red-600 space-y-1 mt-2">
                    @foreach ($errors->get('email') as $message)<li>{{ $message }}</li>@endforeach
                </ul>
            @endif

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        {{ __('Your email address is unverified.') }}
                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>
                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <button type="submit" class="inline-flex items-center px-6 py-3 bg-primary text-on-primary font-headline font-bold text-xs uppercase tracking-widest rounded-sm hover:brightness-110 active:scale-95 transition-all shadow-[0_0_20px_rgba(255,255,255,0.05)]">{{ __('Save') }}</button>

            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)" class="text-sm text-gray-600">{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
