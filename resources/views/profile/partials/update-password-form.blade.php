<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">{{ __('Update Password') }}</h2>
        <p class="mt-1 text-sm text-gray-600">{{ __('Ensure your account is using a long, random password to stay secure.') }}</p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('put')

        <div>
            <label for="update_password_current_password" class="block font-headline text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-2 ml-1">{{ __('Current Password') }}</label>
            <input id="update_password_current_password" class="bg-surface-container-lowest border-none focus:ring-0 text-white font-bold border-b-2 border-transparent focus:border-primary transition-all p-3 rounded-sm mt-1 block w-full" name="current_password" type="password" autocomplete="current-password" />
            @if ($errors->updatePassword->get('current_password'))
                <ul class="text-sm text-red-600 space-y-1 mt-2">
                    @foreach ($errors->updatePassword->get('current_password') as $message)<li>{{ $message }}</li>@endforeach
                </ul>
            @endif
        </div>

        <div>
            <label for="update_password_password" class="block font-headline text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-2 ml-1">{{ __('New Password') }}</label>
            <input id="update_password_password" class="bg-surface-container-lowest border-none focus:ring-0 text-white font-bold border-b-2 border-transparent focus:border-primary transition-all p-3 rounded-sm mt-1 block w-full" name="password" type="password" autocomplete="new-password" />
            @if ($errors->updatePassword->get('password'))
                <ul class="text-sm text-red-600 space-y-1 mt-2">
                    @foreach ($errors->updatePassword->get('password') as $message)<li>{{ $message }}</li>@endforeach
                </ul>
            @endif
        </div>

        <div>
            <label for="update_password_password_confirmation" class="block font-headline text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-2 ml-1">{{ __('Confirm Password') }}</label>
            <input id="update_password_password_confirmation" class="bg-surface-container-lowest border-none focus:ring-0 text-white font-bold border-b-2 border-transparent focus:border-primary transition-all p-3 rounded-sm mt-1 block w-full" name="password_confirmation" type="password" autocomplete="new-password" />
            @if ($errors->updatePassword->get('password_confirmation'))
                <ul class="text-sm text-red-600 space-y-1 mt-2">
                    @foreach ($errors->updatePassword->get('password_confirmation') as $message)<li>{{ $message }}</li>@endforeach
                </ul>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <button type="submit" class="inline-flex items-center px-6 py-3 bg-primary text-on-primary font-headline font-bold text-xs uppercase tracking-widest rounded-sm hover:brightness-110 active:scale-95 transition-all shadow-[0_0_20px_rgba(255,255,255,0.05)]">{{ __('Save') }}</button>

            @if (session('status') === 'password-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)" class="text-sm text-gray-600">{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
