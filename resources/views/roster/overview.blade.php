<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <div class="w-3 h-3 bg-cyan-400 rounded-full shadow-[0_0_8px_#22d3ee]"></div>
                <h2 class="font-headline text-xl text-white leading-tight tracking-tight uppercase">
                    {{ __('Roster Overview:') }} {{ $static->name }}
                </h2>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('statics.roster') }}" class="bg-surface-container-high text-on-surface-variant hover:text-primary px-4 py-2 rounded text-xs font-label uppercase tracking-widest transition-colors border border-white/5">
                    {{ __('Back to Tactical Roster') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-[1800px] mx-auto sm:px-6 lg:px-8">
            <script>
                window.rosterData = {{ Illuminate\Support\Js::from($characters) }};
            </script>
            <div id="app">
                <roster-overview></roster-overview>
            </div>
        </div>
    </div>
</x-app-layout>
