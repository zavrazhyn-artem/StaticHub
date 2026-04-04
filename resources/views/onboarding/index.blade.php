<x-app-layout>
    <div class="mb-12">
        <div class="flex justify-between items-end mb-4">
            <div>
                <span class="text-cyan-400 font-headline text-xs font-bold uppercase tracking-[0.3em] mb-2 block">{{ __('— Tactical Onboarding') }}</span>
                <h2 class="font-headline text-4xl font-black text-white uppercase tracking-tighter italic">
                    {{ __('Establish Your Command') }}
                </h2>
                <p class="text-on-surface-variant font-medium mt-2 max-w-2xl text-sm leading-relaxed">
                    {{ __('Join an existing static or create your own to begin coordinating your raid operations.') }}
                </p>
            </div>
        </div>
    </div>

    <div class="space-y-12">
        @if($isGuildMaster)
            <div class="bg-primary/10 border border-primary/20 p-6 rounded-xl flex items-start gap-4">
                <span class="material-symbols-outlined text-primary text-3xl">workspace_premium</span>
                <div>
                    <h3 class="text-primary font-headline font-bold text-sm uppercase tracking-widest mb-1">{{ __('Guild Master Detected') }}</h3>
                    <p class="text-on-surface-variant text-sm">{{ __('We see you are the Guild Master of') }} <strong>{{ $guildName }}</strong>. {{ __('Would you like to create a Static Group for your guild?') }}</p>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Create Static Section -->
            <div class="bg-surface-container-high p-8 rounded-xl border border-white/5 relative overflow-hidden group">
                <div class="absolute top-0 right-0 w-32 h-32 bg-primary/5 blur-3xl -mr-16 -mt-16 group-hover:bg-primary/10 transition-colors"></div>

                <div class="mb-10 text-center">
                    <div class="text-4xl font-extrabold tracking-tight mb-2">
                        <span class="text-white">Blast</span><span class="text-blue-500">R<span class="text-xs opacity-70 ml-1">r<span class="text-[10px] opacity-50 ml-0.5">r</span></span></span>
                    </div>
                    <p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-[0.2em]">Blast Your Raid</p>
                </div>

                <h3 class="font-headline text-white text-lg font-bold uppercase tracking-widest mb-6 flex items-center gap-3">
                    <span class="material-symbols-outlined text-primary">add_circle</span>
                    {{ __('Create a New Group') }}
                </h3>

                <form action="{{ route('onboarding.create') }}" method="POST" class="space-y-6">
                    @csrf
                    <div>
                        <label class="block text-on-surface-variant text-[10px] font-bold uppercase tracking-widest mb-2" for="name">
                            {{ __('Group Name') }}
                        </label>
                        <input name="name" id="name" type="text" value="{{ $isGuildMaster ? $guildName : '' }}" required
                            class="w-full bg-black/40 border border-white/10 focus:border-primary focus:ring-1 focus:ring-primary text-white py-3 px-4 rounded transition-all outline-none"
                            :placeholder={{ __("Enter group name...") }}>
                    </div>

                    <div>
                        <label class="block text-on-surface-variant text-[10px] font-bold uppercase tracking-widest mb-2" for="region">{{ __('Region') }}</label>
                        <div class="relative">
                            <select name="region" id="region" required
                                class="w-full bg-black/40 border border-white/10 focus:border-primary text-white py-3 px-4 rounded appearance-none cursor-pointer outline-none">
                                <option value="eu">EU</option>
                                <option value="us">US</option>
                            </select>
                            <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant pointer-events-none">expand_more</span>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-primary text-on-primary font-headline font-bold text-xs uppercase tracking-[0.2em] py-4 rounded hover:brightness-110 active:scale-[0.98] transition-all shadow-lg shadow-primary/20">
                        {{ __('Initialize Group') }}
                    </button>
                </form>
            </div>

            <!-- Join Section -->
            <div class="bg-surface-container-low p-8 rounded-xl border border-white/5 flex flex-col justify-center items-center text-center relative overflow-hidden">
                <div class="mb-8">
                    <div class="w-20 h-20 bg-white/5 rounded-full flex items-center justify-center mb-4 mx-auto">
                        <span class="material-symbols-outlined text-4xl text-on-surface-variant">group_add</span>
                    </div>
                    <h3 class="font-headline text-white text-lg font-bold uppercase tracking-widest mb-2">{{ __('Joining a Group?') }}</h3>
                    <p class="text-on-surface-variant text-sm max-w-xs mx-auto">
                        {{ __('Ask your Raid Leader for an invite link. It should look like this:') }}
                    </p>
                </div>

                <div class="w-full bg-black/40 border border-dashed border-white/10 p-4 rounded-lg mb-8">
                    <code class="text-primary text-xs font-mono break-all">{{ url('/join/TOKEN') }}</code>
                </div>

                <div class="flex items-center gap-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/50">
                    <span class="material-symbols-outlined text-sm">info</span>
                    {{ __("Once you click the link, you'll be able to select your character.") }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
