<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Join Static: ') }} {{ $static->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="max-w-md mx-auto">
                    <h3 class="text-lg font-bold mb-4 text-center">You've been invited to join {{ $static->name }}</h3>

                    <form action="{{ route('statics.join.process', $static->invite_token) }}" method="POST">
                        @csrf
                        <div class="mb-6">
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="character_id">
                                Select which character will join this static:
                            </label>
                            <select name="character_id" id="character_id" required
                                class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                @foreach($userCharacters as $character)
                                    <option value="{{ $character->id }}">{{ $character->name }} - {{ $character->realm->name ?? '' }}</option>
                                @endforeach
                            </select>
                            @error('character_id')
                                <p class="text-red-500 text-xs italic mt-2">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center justify-between">
                            <button type="submit" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full">
                                Join Static
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
