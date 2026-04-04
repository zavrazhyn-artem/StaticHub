<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-12 flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div>
                <div class="flex items-center gap-3 mb-2 text-amber-500 font-headline text-[10px] font-black uppercase tracking-[0.3em]">
                    <span class="material-symbols-outlined text-lg">terminal</span>
                    Mission Intelligence Hub
                </div>
                <h1 class="text-5xl font-black text-white uppercase tracking-tighter font-headline leading-none">Tactical Logs</h1>
                <p class="text-on-surface-variant font-medium mt-2 uppercase tracking-widest text-xs flex items-center gap-2">
                    {{ $static->name }}
                    <span class="opacity-20">•</span>
                    Performance Archives
                </p>
            </div>

            <div class="flex items-center gap-4">
                <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'manual-log-modal' }))"
                    class="flex items-center gap-2 bg-amber-500/10 border border-amber-500/30 hover:bg-amber-500 hover:text-black px-5 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-[0.2em] transition-all text-amber-500">
                    <span class="material-symbols-outlined text-sm">upload_file</span>
                    Process Manual Log
                </button>

                <form action="{{ route('statics.logs.index', $static) }}" method="GET" class="flex items-center gap-2">
                    <select name="difficulty" onchange="this.form.submit()"
                        class="bg-surface-container-low border border-amber-500/20 rounded-lg font-headline text-[10px] font-bold text-amber-500 uppercase tracking-widest focus:ring-amber-500 focus:border-amber-500 px-4 py-2 outline-none appearance-none cursor-pointer hover:bg-amber-500/5 transition-colors">
                        <option value="">All Difficulties</option>
                        <option value="Normal" {{ request('difficulty') == 'Normal' ? 'selected' : '' }}>Normal</option>
                        <option value="Heroic" {{ request('difficulty') == 'Heroic' ? 'selected' : '' }}>Heroic</option>
                        <option value="Mythic" {{ request('difficulty') == 'Mythic' ? 'selected' : '' }}>Mythic</option>
                    </select>
                </form>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            @forelse($logs as $log)
                <div class="group relative bg-surface-container-low border border-white/5 rounded-2xl overflow-hidden hover:border-amber-500/30 transition-all duration-500 hover:shadow-[0_0_40px_-15px_rgba(245,158,11,0.2)]">
                    <!-- Top Accents -->
                    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-amber-500/20 to-transparent"></div>

                    <div class="p-6 space-y-6">
                        <!-- Header -->
                        <div class="flex justify-between items-start">
                            <div class="space-y-1">
                                <div class="text-[10px] font-black text-on-surface-variant uppercase tracking-widest opacity-60">
                                    {{ $log->created_at->format('M d, Y') }}
                                </div>
                                <h3 class="text-xl font-black text-white uppercase tracking-tight group-hover:text-amber-500 transition-colors">
                                    {{ $log->title ?? 'Manual Log Analysis' }}
                                </h3>
                            </div>

                            @if($log->ai_analysis)
                                <span class="flex items-center gap-1.5 px-2.5 py-1 bg-success-neon/10 border border-success-neon/20 rounded-full text-[9px] font-black text-success-neon uppercase tracking-widest">
                                    <span class="w-1 h-1 bg-success-neon rounded-full animate-pulse"></span>
                                    Analyzed
                                </span>
                            @else
                                <span class="flex items-center gap-1.5 px-2.5 py-1 bg-amber-500/10 border border-amber-500/20 rounded-full text-[9px] font-black text-amber-500 uppercase tracking-widest">
                                    <span class="w-1 h-1 bg-amber-500 rounded-full animate-pulse"></span>
                                    Pending AI
                                </span>
                            @endif
                        </div>

                        <!-- Stats & Footer -->
                        <div class="flex items-center justify-between pt-2">
                            <div class="flex gap-4">
                                <div class="text-center">
                                    <div class="text-[10px] font-black text-white">WCL</div>
                                    <div class="text-[8px] font-bold text-on-surface-variant uppercase tracking-tighter opacity-60">Report</div>
                                </div>
                            </div>

                            <a href="{{ route('statics.logs.show', [$static, $log]) }}" class="flex items-center gap-2 bg-white/5 hover:bg-amber-500 hover:text-black px-4 py-2 rounded-lg text-[10px] font-black uppercase tracking-[0.15em] transition-all group/btn">
                                Open Files
                                <span class="material-symbols-outlined text-sm group-hover/btn:translate-x-1 transition-transform">arrow_forward</span>
                            </a>
                        </div>
                    </div>

                    <!-- Decorative Corner -->
                    <div class="absolute bottom-0 right-0 w-8 h-8 opacity-5">
                        <div class="absolute bottom-0 right-0 w-full h-[1px] bg-amber-500"></div>
                        <div class="absolute bottom-0 right-0 h-full w-[1px] bg-amber-500"></div>
                    </div>
                </div>
            @empty
                <div class="col-span-full py-24 text-center border-2 border-dashed border-white/5 rounded-3xl">
                    <span class="material-symbols-outlined text-6xl text-white/10 mb-4">folder_off</span>
                    <h3 class="text-xl font-black text-white uppercase tracking-widest">No Intelligence Data Found</h3>
                    <p class="text-on-surface-variant mt-2 uppercase tracking-wider text-xs">Matching reports have not been processed for this static yet.</p>
                </div>
            @endforelse
        </div>

        <div class="mt-12">
            {{ $logs->links() }}
        </div>

        <x-modal name="manual-log-modal" focusable>
            <form method="post" action="{{ route('statics.logs.manual.store', $static) }}" class="p-8 bg-surface-container-highest border border-white/5">
                @csrf
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-3 bg-amber-500/10 rounded-xl">
                        <span class="material-symbols-outlined text-amber-500">upload_file</span>
                    </div>
                    <div>
                        <h2 class="text-2xl font-black text-white uppercase tracking-tight leading-none">Manual Log Submission</h2>
                        <p class="text-on-surface-variant font-bold text-[10px] uppercase tracking-widest mt-1">Tactical Analysis Pipeline</p>
                    </div>
                </div>

                <div class="space-y-6">
                    <div>
                        <x-input-label for="wcl_url" value="WCL Report URL" class="text-amber-500/60 uppercase tracking-widest text-[10px] font-black mb-2" />
                        <x-text-input id="wcl_url" name="wcl_url" type="text" class="w-full bg-black/20 border border-white/10 p-4" placeholder="https://www.warcraftlogs.com/reports/..." required />
                        <p class="mt-2 text-[9px] text-on-surface-variant font-bold uppercase tracking-widest opacity-40">
                            Example: https://www.warcraftlogs.com/reports/aBcDeFg123456789
                        </p>
                        <x-input-error :messages="$errors->get('wcl_url')" class="mt-2" />
                    </div>
                </div>

                <div class="mt-10 flex justify-end gap-3">
                    <button type="button" x-on:click="$dispatch('close')" class="px-6 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest text-on-surface-variant hover:bg-white/5 transition-all">
                        Cancel
                    </button>
                    <button type="submit" class="bg-amber-500 hover:bg-amber-400 text-black px-8 py-3 rounded-xl text-[10px] font-black uppercase tracking-[0.2em] shadow-lg shadow-amber-500/20 transition-all active:scale-95">
                        Submit for Analysis
                    </button>
                </div>
            </form>
        </x-modal>
    </div>
</x-app-layout>
