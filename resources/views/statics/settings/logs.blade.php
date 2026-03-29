<x-app-layout>
    <div class="max-w-4xl mx-auto">
        <div class="mb-8">
            <h1 class="text-4xl font-black text-white uppercase tracking-tighter font-headline">Static Settings</h1>
            <p class="text-on-surface-variant font-medium mt-1 uppercase tracking-widest text-xs">{{ $static->name }}</p>
        </div>

        @include('statics.settings._tabs')

        <div class="bg-surface-container-low border border-white/5 rounded-xl p-8 shadow-2xl backdrop-blur-sm">
            <form action="{{ route('statics.settings.logs.update', $static) }}" method="POST" class="space-y-8">
                @csrf

                <div class="space-y-6">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-[#ff7d0a]/10 flex items-center justify-center border border-[#ff7d0a]/20">
                            <span class="material-symbols-outlined text-[#ff7d0a]">analytics</span>
                        </div>
                        <div>
                            <h3 class="text-white font-headline text-sm font-black uppercase tracking-widest">Warcraft Logs Integration</h3>
                            <p class="text-[10px] text-on-surface-variant font-medium uppercase tracking-wider">Configure your guild details to enable automated log fetching.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- WCL Guild ID -->
                        <div class="space-y-2">
                            <label for="wcl_guild_id" class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Guild ID</label>
                            <div class="relative group">
                                <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <span class="material-symbols-outlined text-on-surface-variant group-focus-within:text-[#ff7d0a] transition-colors text-lg">fingerprint</span>
                                </span>
                                <input type="text" name="wcl_guild_id" id="wcl_guild_id"
                                    value="{{ $static->wcl_guild_id }}"
                                    placeholder="e.g. 123456"
                                    class="block w-full pl-12 pr-4 py-3 bg-surface-container-highest border border-white/5 rounded-lg font-headline text-sm font-bold text-white tracking-widest focus:ring-2 focus:ring-[#ff7d0a] focus:border-transparent transition-all outline-none">
                            </div>
                            <p class="text-[9px] text-on-surface-variant/60 font-medium uppercase tracking-wider">Found in your WCL guild URL.</p>
                        </div>

                        <!-- WCL Region -->
                        <div class="space-y-2">
                            <label for="wcl_region" class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Region</label>
                            <div class="relative group">
                                <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <span class="material-symbols-outlined text-on-surface-variant group-focus-within:text-[#ff7d0a] transition-colors text-lg">public</span>
                                </span>
                                <select name="wcl_region" id="wcl_region"
                                    class="block w-full pl-12 pr-4 py-3 bg-surface-container-highest border border-white/5 rounded-lg font-headline text-sm font-bold text-white tracking-widest focus:ring-2 focus:ring-[#ff7d0a] focus:border-transparent transition-all outline-none appearance-none">
                                    <option value="us" {{ $static->wcl_region == 'us' ? 'selected' : '' }}>US</option>
                                    <option value="eu" {{ ($static->wcl_region ?? $static->region) == 'eu' ? 'selected' : '' }}>EU</option>
                                    <option value="kr" {{ $static->wcl_region == 'kr' ? 'selected' : '' }}>KR</option>
                                    <option value="tw" {{ $static->wcl_region == 'tw' ? 'selected' : '' }}>TW</option>
                                    <option value="cn" {{ $static->wcl_region == 'cn' ? 'selected' : '' }}>CN</option>
                                </select>
                            </div>
                        </div>

                        <!-- WCL Realm -->
                        <div class="space-y-2">
                            <label for="wcl_realm" class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Realm (Slug)</label>
                            <div class="relative group">
                                <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <span class="material-symbols-outlined text-on-surface-variant group-focus-within:text-[#ff7d0a] transition-colors text-lg">dns</span>
                                </span>
                                <input type="text" name="wcl_realm" id="wcl_realm"
                                    value="{{ $static->wcl_realm ?? $static->server }}"
                                    placeholder="e.g. kazzak"
                                    class="block w-full pl-12 pr-4 py-3 bg-surface-container-highest border border-white/5 rounded-lg font-headline text-sm font-bold text-white tracking-widest focus:ring-2 focus:ring-[#ff7d0a] focus:border-transparent transition-all outline-none">
                            </div>
                            <p class="text-[9px] text-on-surface-variant/60 font-medium uppercase tracking-wider">Lowercase, use hyphens for spaces.</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-6 pt-8 border-t border-white/5">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center border border-primary/20">
                            <span class="material-symbols-outlined text-primary">psychology</span>
                        </div>
                        <div>
                            <h3 class="text-white font-headline text-sm font-black uppercase tracking-widest">AI Tactical Analyst</h3>
                            <p class="text-[10px] text-on-surface-variant font-medium uppercase tracking-wider">Automated performance reviews powered by Gemini Flash.</p>
                        </div>
                    </div>

                    <div class="bg-black/20 rounded-xl p-6 border border-white/5 space-y-4">
                        <div class="flex items-start gap-4">
                            <span class="material-symbols-outlined text-success-neon text-xl mt-1">check_circle</span>
                            <div>
                                <p class="text-sm text-white font-bold uppercase tracking-tighter">Status: Active</p>
                                <p class="text-xs text-on-surface-variant mt-1">AI analysis will be automatically triggered after each raid once logs are detected. Results will be posted to Discord and available in the raid details.</p>
                            </div>
                        </div>
                    </div>
                </div>

                @if(session('success'))
                    <div class="bg-success-neon/10 border border-success-neon/20 rounded-lg p-4 flex items-center gap-3 text-success-neon font-headline text-[10px] font-bold uppercase tracking-widest">
                        <span class="material-symbols-outlined text-sm">check_circle</span>
                        {{ session('success') }}
                    </div>
                @endif

                <div class="pt-4 border-t border-white/5">
                    <button type="submit" class="bg-[#ff7d0a] text-black px-8 py-3 rounded-sm font-headline text-xs font-bold uppercase tracking-[0.2em] hover:brightness-110 active:scale-95 transition-all flex items-center gap-3">
                        <span class="material-symbols-outlined text-lg">save</span>
                        Save Log Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
