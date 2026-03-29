<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Welcome! Let\'s get started') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

                @if($isGuildMaster)
                    <div class="mb-8 p-4 bg-blue-50 border-l-4 border-blue-500 text-blue-700">
                        <p class="font-bold">Guild Master detected!</p>
                        <p>We see you are the Guild Master of <strong>{{ $guildName }}</strong>. Would you like to create a Static Group for your guild?</p>
                    </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Create Static Section -->
                    <div class="border p-6 rounded-lg">
                        <h3 class="text-lg font-bold mb-4">Create a New Static</h3>
                        <form action="{{ route('onboarding.create') }}" method="POST">
                            @csrf
                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="name">
                                    Static Name
                                </label>
                                <input name="name" id="name" type="text" value="{{ $isGuildMaster ? $guildName : '' }}" required
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>

                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="character_id">
                                    Select Your Main Character
                                </label>
                                <select name="character_id" id="character_id" required
                                    class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                    @foreach($userCharacters as $character)
                                        <option value="{{ $character->id }}">{{ $character->name }} - {{ $character->realm->name ?? '' }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="region">Region</label>
                                    <select name="region" id="region" required class="shadow border rounded w-full py-2 px-3">
                                        <option value="eu">EU</option>
                                        <option value="us">US</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="faction">Faction</label>
                                    <select name="faction" id="faction" required class="shadow border rounded w-full py-2 px-3">
                                        <option value="horde">Horde</option>
                                        <option value="alliance">Alliance</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="server">Server</label>
                                <input name="server" id="server" type="text" placeholder="e.g. Draenor" required
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700">
                            </div>

                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full">
                                Create Static
                            </button>
                        </form>
                    </div>

                    <!-- Join Section -->
                    <div class="border p-6 rounded-lg flex flex-col justify-center items-center bg-gray-50">
                        <h3 class="text-lg font-bold mb-4">Joining an existing Static?</h3>
                        <p class="text-gray-600 mb-6 text-center">
                            Ask your Raid Leader for an invite link. It should look like this:<br>
                            <code>{{ url('/join/TOKEN') }}</code>
                        </p>
                        <div class="text-sm text-gray-500 italic">
                            Once you click the link, you'll be able to select your character and join.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
