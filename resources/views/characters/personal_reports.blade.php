<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-12">
            <h1 class="text-6xl font-black text-white uppercase tracking-tighter font-headline leading-none mb-4">
                {{ __('Personal Intelligence') }}
            </h1>
            <p class="text-on-surface-variant font-bold uppercase tracking-wider text-xs">
                {{ __('AI-driven tactical feedback for your characters across all raids.') }}
            </p>
        </div>

        @if($reports->isEmpty())
            <div class="py-24 text-center border-2 border-dashed border-white/5 rounded-3xl">
                <span class="material-symbols-outlined text-8xl text-white/5 mb-6">psychology</span>
                <h3 class="text-2xl font-black text-white uppercase tracking-widest">{{ __('No Intelligence Data') }}</h3>
                <p class="text-on-surface-variant mt-4 max-w-md mx-auto uppercase tracking-wider text-xs leading-relaxed">
                    {{ __("You don't have any personal AI reports yet. Reports are generated after raid logs are analyzed by the Raid Leader.") }}
                </p>
            </div>
        @else
            <div class="space-y-12">
                @foreach($reports as $report)
                    @php
                        $classColor = strtolower(str_replace(' ', '-', $report->character->playable_class));
                        $raidTitle = $report->tacticalReport->title ?? __('Raid Encounter');
                        $date = $report->created_at->format('F d, Y');
                    @endphp
                    <div class="bg-surface-container-low border border-white/5 rounded-3xl overflow-hidden shadow-2xl">
                        <div class="bg-{{ $classColor }}/5 border-b border-white/5 px-8 py-6 flex flex-col md:flex-row md:items-center justify-between gap-6">
                            <div class="flex items-center gap-4">
                                <div class="w-14 h-14 rounded-2xl bg-{{ $classColor }}/20 flex items-center justify-center border border-{{ $classColor }}/30">
                                    <span class="material-symbols-outlined text-2xl text-{{ $classColor }}">person</span>
                                </div>
                                <div>
                                    <h3 class="text-xl font-black text-white uppercase tracking-tight">{{ $report->character->name }}</h3>
                                    <p class="text-[10px] font-black text-{{ $classColor }} uppercase tracking-[0.2em]">{{ $report->character->playable_class }}</p>
                                </div>
                            </div>
                            <div class="flex flex-col md:items-end">
                                <span class="text-xs font-black text-white uppercase tracking-widest">{{ $raidTitle }}</span>
                                <span class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider">{{ $date }}</span>
                            </div>
                        </div>
                        <div class="p-8">
                            <div class="prose prose-invert prose-tactical max-w-none">
                                {!! \Illuminate\Support\Str::markdown($report->content) !!}
                            </div>
                        </div>
                        <div class="px-8 py-4 bg-black/20 border-t border-white/5 flex justify-end">
                            <a href="{{ route('statics.logs.show', [$report->tacticalReport->static_id, $report->tacticalReport]) }}" class="text-[10px] font-black text-amber-500 uppercase tracking-widest hover:underline flex items-center gap-1">
                                {{ __('View Full Tactical Report') }}
                                <span class="material-symbols-outlined text-sm">arrow_forward</span>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
