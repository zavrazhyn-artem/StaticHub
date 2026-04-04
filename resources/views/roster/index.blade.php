<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <div class="w-3 h-3 bg-success-neon rounded-full shadow-[0_0_8px_#39FF14]"></div>
                <h2 class="font-headline text-xl text-on-surface leading-tight tracking-tight uppercase">
                    {{ __('Tactical Roster:') }} {{ $static->name }}
                </h2>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-[1600px] mx-auto sm:px-6 lg:px-8">
            <unified-roster
                :static-id="{{ $static->id }}"
                :initial-data='@json($rosterData)'
            ></unified-roster>
        </div>
    </div>
</x-app-layout>
