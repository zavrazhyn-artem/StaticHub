<x-app-layout>
    <div class="mb-12">
        <div class="flex justify-between items-end mb-4">
            <div>
                <span class="text-cyan-400 font-headline text-xs font-bold uppercase tracking-[0.3em] mb-2 block">— Command Authorization</span>
                <h2 class="font-headline text-4xl font-black text-white uppercase tracking-tighter italic">
                    Join {{ $static->name }}
                </h2>
                <p class="text-on-surface-variant font-medium mt-2 max-w-2xl text-sm leading-relaxed">
                    You have been authorized to join this static operations group.
                </p>
            </div>
        </div>
    </div>

    <div class="max-w-2xl mx-auto">
        <div class="bg-surface-container-high p-12 rounded-xl border border-white/5 text-center relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-primary to-transparent"></div>

            <div class="w-24 h-24 bg-primary/10 rounded-full flex items-center justify-center mb-8 mx-auto border border-primary/20">
                <span class="material-symbols-outlined text-5xl text-primary">diversity_3</span>
            </div>

            <h3 class="font-headline text-white text-2xl font-black uppercase tracking-tight mb-4">Invitation Received</h3>
            <p class="text-on-surface-variant mb-12">
                By joining <strong>{{ $static->name }}</strong> ({{ strtoupper($static->region) }} - {{ $static->server }}), you will be able to participate in their raid events and coordinate with other members.
            </p>

            <form action="{{ route('statics.join.process', $static->invite_token) }}" method="POST">
                @csrf
                <button type="submit" class="w-full bg-primary text-on-primary font-headline font-bold text-sm uppercase tracking-[0.2em] py-5 rounded hover:brightness-110 active:scale-[0.98] transition-all shadow-xl shadow-primary/20 flex items-center justify-center gap-3">
                    <span class="material-symbols-outlined">login</span>
                    Confirm Participation
                </button>
            </form>

            <div class="mt-8 pt-8 border-t border-white/5">
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/50">
                    Step 1: Join Group • Step 2: Select Character
                </p>
            </div>
        </div>
    </div>
</x-app-layout>
