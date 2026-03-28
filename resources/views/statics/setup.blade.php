<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Setup your Static') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <section>
                        <header>
                            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                {{ __('Create a New Static') }}
                            </h2>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                {{ __('Manually create a new raiding group.') }}
                            </p>
                        </header>

                        <form method="post" action="{{ route('statics.store') }}" class="mt-6 space-y-6">
                            @csrf
                            <div>
                                <x-input-label for="name" :value="__('Static Name')" />
                                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" required />
                                <x-input-error class="mt-2" :messages="$errors->get('name')" />
                            </div>

                            <div>
                                <x-input-label for="realm_slug" :value="__('Server')" />
                                <select id="realm_slug" name="realm_slug" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" required>
                                    <option value="">{{ __('Select a Server') }}</option>
                                    @foreach($realms as $realm)
                                        <option value="{{ $realm->slug }}">{{ $realm->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error class="mt-2" :messages="$errors->get('realm_slug')" />
                            </div>

                            <div>
                                <x-input-label for="region" :value="__('Region')" />
                                <select id="region" name="region" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                    <option value="eu">Europe</option>
                                    <option value="us">Americas</option>
                                    <option value="kr">Korea</option>
                                    <option value="tw">Taiwan</option>
                                </select>
                                <x-input-error class="mt-2" :messages="$errors->get('region')" />
                            </div>

                            <div class="flex items-center gap-4">
                                <x-primary-button>{{ __('Create Static') }}</x-primary-button>
                            </div>
                        </form>
                    </section>
                </div>
            </div>

            @if(!empty($guilds))
            <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <section>
                        <header>
                            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                {{ __('Import from Battle.net') }}
                            </h2>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                {{ __('We found these guilds on your account. Import them as a Static.') }}
                            </p>
                        </header>

                        <div class="mt-6 space-y-4">
                            @foreach($guilds as $guild)
                                <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <div>
                                        <p class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ $guild['name'] }}</p>
                                        <p class="text-xs text-gray-600 dark:text-gray-400">{{ $guild['realm'] }}</p>
                                    </div>
                                    <form method="post" action="{{ route('statics.import') }}">
                                        @csrf
                                        <input type="hidden" name="name" value="{{ $guild['name'] }}">
                                        <input type="hidden" name="realm_slug" value="{{ $guild['realm_slug'] }}">
                                        <input type="hidden" name="realm" value="{{ $guild['realm'] }}">
                                        <x-secondary-button type="submit">
                                            {{ __('Import Guild') }}
                                        </x-secondary-button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    </section>
                </div>
            </div>
            @endif
        </div>
    </div>
</x-app-layout>
