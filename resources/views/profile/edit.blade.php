<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            <!-- Integrations Section -->
            <div class="p-4 sm:p-8 bg-surface-container-high border border-white/5 rounded-xl shadow-2xl">
                <div class="max-w-xl">
                    <header class="mb-6">
                        <h2 class="font-headline text-lg font-bold text-white uppercase tracking-widest">
                            {{ __('Integrations') }}
                        </h2>
                        <p class="mt-1 text-xs font-bold text-gray-500 uppercase tracking-widest">
                            {{ __('Connect your external accounts for enhanced functionality.') }}
                        </p>
                    </header>

                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 bg-surface-container-lowest border border-white/5 rounded-lg transition-all hover:bg-surface-container-low group">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 flex items-center justify-center rounded-lg bg-[#5865F2]/10 text-[#5865F2] group-hover:bg-[#5865F2] group-hover:text-white transition-all">
                                    <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24">
                                        <path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037 19.736 19.736 0 0 0-4.885 1.515.069.069 0 0 0-.032.027C.533 9.048-.32 13.572.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.927 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="font-headline text-[10px] font-bold text-white uppercase tracking-widest">Discord</div>
                                    @if(auth()->user()->discord_id)
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span class="text-xs font-bold text-success-neon">{{ auth()->user()->discord_username }}</span>
                                            <span class="material-symbols-outlined text-success-neon text-sm">check_circle</span>
                                        </div>
                                    @else
                                        <div class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mt-0.5">Not Connected</div>
                                    @endif
                                </div>
                            </div>

                            @if(auth()->user()->discord_id)
                                <form method="POST" action="{{ route('profile.discord.unlink') }}">
                                    @csrf
                                    <button type="submit" class="bg-red-500/10 hover:bg-red-500 text-red-500 hover:text-white font-headline text-[10px] font-bold uppercase tracking-widest py-2 px-4 rounded-sm transition-all active:scale-95">
                                        Unlink
                                    </button>
                                </form>
                            @else
                                <a href="{{ route('profile.discord.link') }}" class="bg-[#5865F2] hover:bg-[#4752C4] text-white font-headline text-[10px] font-bold uppercase tracking-widest py-2 px-4 rounded-sm transition-all active:scale-95 shadow-[0_0_15px_rgba(88,101,242,0.3)] hover:shadow-[0_0_20px_rgba(88,101,242,0.5)]">
                                    Link Account
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-surface-container-high border border-white/5 rounded-xl shadow-2xl">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
