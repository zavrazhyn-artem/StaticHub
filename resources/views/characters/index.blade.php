<x-app-layout>
    <div class="mb-12">
        <div class="flex justify-between items-end mb-4">
            <div>
                <span class="text-cyan-400 font-headline text-xs font-bold uppercase tracking-[0.3em] mb-2 block">{{ __('— Strategic Command') }}</span>
                <h2 class="font-headline text-4xl font-black text-white uppercase tracking-tight italic">
                    {{ __('Static Participation') }}
                </h2>
                <p class="text-on-surface-variant font-medium mt-2 max-w-2xl text-sm leading-relaxed">
                    {{ __('Manage your active roster for the current Mythic tier. Designate your main and alts to assist the Guild Master in raid composition planning.') }}
                </p>
            </div>
            <form action="{{ route('characters.import') }}" method="POST">
                @csrf
                <button type="submit" class="flex items-center gap-2 bg-surface-container-highest border border-white/5 text-white px-6 py-3 font-headline font-bold text-xs uppercase tracking-widest rounded hover:bg-surface-bright active:scale-95 transition-all">
                    <span class="material-symbols-outlined text-sm">sync</span>
                    {{ __('Sync Battle.net') }}
                </button>
            </form>
        </div>
    </div>

    <div class="space-y-12">
        @if(session('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
                 class="bg-success-neon/10 border border-success-neon/20 p-4 rounded-lg flex items-center gap-3">
                <span class="material-symbols-outlined text-success-neon">check_circle</span>
                <span class="text-success-neon text-xs font-bold uppercase tracking-widest">{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="bg-error-dim/10 border border-error-dim/20 p-4 rounded-lg flex items-center gap-3">
                <span class="material-symbols-outlined text-error-dim">error</span>
                <span class="text-error-dim text-xs font-bold uppercase tracking-widest">{{ session('error') }}</span>
            </div>
        @endif

        @if($characters->isEmpty())
            <div class="bg-surface-container-low p-12 rounded-xl border border-white/5 text-center">
                <span class="material-symbols-outlined text-6xl text-on-surface-variant mb-4">group_off</span>
                <p class="text-on-surface-variant font-headline text-sm font-bold uppercase tracking-widest">{{ __('No characters found. Click the sync button to import them.') }}</p>
            </div>
        @elseif(!$static)
            <div class="bg-surface-container-low p-12 rounded-xl border border-white/5 text-center">
                <span class="material-symbols-outlined text-6xl text-on-surface-variant mb-4">error</span>
                <p class="text-on-surface-variant font-headline text-sm font-bold uppercase tracking-widest">{{ __('You must belong to at least one static to manage roster participation.') }}</p>
            </div>
        @else
            <characters-page
                :characters="{{ $characters->load('realm')->toJson() }}"
                :static-id="{{ $static->id }}"
                :initial-main="{{ $mainCharId ?? 'null' }}"
                :initial-raiding="{{ json_encode($raidingCharIds) }}"
                :specializations="{{ $specializations->values()->toJson() }}"
                :character-specs="{{ json_encode($characterSpecs) }}"
                save-route="{{ route('roster.updateParticipation', $static->id) }}"
                spec-save-route="{{ route('characters.specs.update') }}"
                csrf-token="{{ csrf_token() }}"
            ></characters-page>
        @endif
    </div>
</x-app-layout>
