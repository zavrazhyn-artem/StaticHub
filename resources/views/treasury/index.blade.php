<x-app-layout>
    <div class="space-y-6" x-data="{ showDepositModal: false, transactionType: 'deposit' }">
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <h1 class="text-4xl font-black text-white uppercase tracking-tighter font-headline">Guild Treasury</h1>
                <p class="text-on-surface-variant font-medium mt-1 uppercase tracking-widest text-xs">{{ $static->name }} • Financial Ledger</p>
            </div>

            <div class="flex items-center gap-2">
                <button @click="transactionType = 'deposit'; showDepositModal = true"
                   class="bg-primary text-on-primary hover:brightness-110 px-4 py-2 rounded-sm font-headline text-[10px] font-bold uppercase tracking-widest transition-all flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">add_circle</span>
                    Record Deposit
                </button>
                <button @click="transactionType = 'withdrawal'; showDepositModal = true"
                   class="bg-surface-container-high text-on-surface-variant hover:text-white px-4 py-2 rounded-sm font-headline text-[10px] font-bold uppercase tracking-widest transition-colors flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">remove_circle</span>
                    Record Withdrawal
                </button>
            </div>
        </div>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Total Reserves -->
            <div class="bg-surface-container-high border border-white/5 rounded-xl p-6 shadow-2xl backdrop-blur-sm relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                    <span class="material-symbols-outlined text-6xl text-primary">savings</span>
                </div>
                <h3 class="text-on-surface-variant font-headline text-[10px] font-bold uppercase tracking-widest mb-4">Total Reserves</h3>
                <div class="flex items-baseline gap-2">
                    <span class="text-4xl font-black text-[#FFD700] tracking-tighter font-headline">{{ \App\Helpers\CurrencyHelper::formatGold($reserves, false) }}</span>
                    <span class="text-xs font-bold text-[#FFD700]/60 uppercase tracking-widest">Gold</span>
                </div>
            </div>

            <!-- Weekly Tax Goal -->
            <div class="bg-surface-container-high border border-white/5 rounded-xl p-6 shadow-2xl backdrop-blur-sm relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                    <span class="material-symbols-outlined text-6xl text-primary">payments</span>
                </div>
                <h3 class="text-on-surface-variant font-headline text-[10px] font-bold uppercase tracking-widest mb-4">Required Weekly Tax</h3>
                <div class="flex items-baseline gap-2">
                    <span class="text-4xl font-black {{ $taxStatus === 'danger' ? 'text-error' : ($taxStatus === 'warning' ? 'text-warning' : 'text-white') }} tracking-tighter font-headline">{{ \App\Helpers\CurrencyHelper::formatGold($targetTax, false) }}</span>
                    <span class="text-xs font-bold text-on-surface-variant uppercase tracking-widest">Gold / Player</span>
                </div>
                <div class="mt-2 text-[10px] {{ $taxStatus === 'danger' ? 'text-error' : ($taxStatus === 'warning' ? 'text-warning' : 'text-on-surface-variant') }} uppercase font-bold tracking-widest">
                    {{ $taxDescription }}
                </div>
            </div>

            <!-- Financial Health -->
            <div class="bg-surface-container-high border border-white/5 rounded-xl p-6 shadow-2xl backdrop-blur-sm relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                    <span class="material-symbols-outlined text-6xl text-primary">analytics</span>
                </div>
                <h3 class="text-on-surface-variant font-headline text-[10px] font-bold uppercase tracking-widest mb-4">Financial Health</h3>
                <div class="flex items-baseline gap-2">
                    <span class="text-4xl font-black {{ $autonomy > 2 ? 'text-success-neon' : 'text-error' }} tracking-tighter font-headline">{{ $autonomy }}</span>
                    <span class="text-xs font-bold text-on-surface-variant uppercase tracking-widest">Weeks of Autonomy</span>
                </div>
                @if($weeklyCost == 0)
                    <div class="mt-2 text-[10px] text-error uppercase font-bold tracking-widest flex items-center gap-1">
                        <span class="material-symbols-outlined text-xs">warning</span>
                        Configure Consumables Planning
                    </div>
                @else
                    <div class="mt-2 text-[10px] text-on-surface-variant uppercase font-bold tracking-widest">
                        Weekly Cost: {{ \App\Helpers\CurrencyHelper::formatGold($weeklyCost, false) }} G
                    </div>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Weekly Tax Status -->
            <div class="lg:col-span-1 space-y-4">
                <div class="bg-surface-container border border-white/5 rounded-xl overflow-hidden shadow-2xl backdrop-blur-sm">
                    <div class="px-6 py-4 border-b border-white/5 bg-surface-container-high">
                        <h3 class="font-headline text-xs font-bold text-white uppercase tracking-widest">Weekly Tax Tracking</h3>
                    </div>
                    <div class="p-4 space-y-2 max-h-[500px] overflow-y-auto">
                        @foreach($weeklyStatus as $status)
                            <div class="flex items-center justify-between p-3 rounded-sm bg-surface-container-lowest border border-white/5">
                                <div class="flex items-center gap-3">
                                    <div class="w-2 h-2 rounded-full {{ $status['is_paid'] ? 'bg-success-neon shadow-[0_0_8px_rgba(0,255,153,0.5)]' : 'bg-error shadow-[0_0_8px_rgba(255,68,68,0.5)]' }}"></div>
                                    <span class="text-sm font-medium text-white">{{ $status['name'] }}</span>
                                </div>
                                <div class="text-right">
                                    <div class="text-xs font-bold {{ $status['is_paid'] ? 'text-success-neon' : 'text-on-surface-variant' }}">
                                        {{ \App\Helpers\CurrencyHelper::formatGold($status['total_paid'], false) }} / {{ \App\Helpers\CurrencyHelper::formatGold($targetTax ?: 0, false) }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Recent Ledger -->
            <div class="lg:col-span-2">
                <div class="bg-surface-container border border-white/5 rounded-xl overflow-hidden shadow-2xl backdrop-blur-sm">
                    <div class="px-6 py-4 border-b border-white/5 bg-surface-container-high flex justify-between items-center">
                        <h3 class="font-headline text-xs font-bold text-white uppercase tracking-widest">Recent Transactions</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-surface-container-highest border-b border-white/5">
                                    <th class="px-6 py-3 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest font-headline">Date</th>
                                    <th class="px-6 py-3 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest font-headline">Member</th>
                                    <th class="px-6 py-3 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest font-headline">Description</th>
                                    <th class="px-6 py-3 text-right text-[10px] font-bold text-on-surface-variant uppercase tracking-widest font-headline">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @forelse($recentTransactions as $tx)
                                    <tr class="hover:bg-white/5 transition-colors group">
                                        <td class="px-6 py-4 text-xs text-on-surface-variant font-medium">
                                            {{ $tx->created_at->format('M d, H:i') }}
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-sm font-bold text-white">{{ $tx->user->name }}</span>
                                        </td>
                                        <td class="px-6 py-4 text-xs text-on-surface-variant italic">
                                            {{ $tx->description ?? '-' }}
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <span class="text-sm font-black {{ $tx->type === 'deposit' ? 'text-[#FFD700]' : 'text-error' }} font-headline tracking-tight">
                                                {{ $tx->type === 'deposit' ? '+' : '-' }}{{ \App\Helpers\CurrencyHelper::formatGold($tx->amount, false) }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-6 py-12 text-center text-on-surface-variant italic text-sm">
                                            No transactions recorded yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transaction Modal -->
        <div x-show="showDepositModal"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
             style="display: none;">

            <div @click.away="showDepositModal = false" class="w-full max-w-md bg-surface-container border border-white/10 rounded-xl shadow-2xl overflow-hidden glassmorphism">
                <div class="px-6 py-4 border-b border-white/5 flex justify-between items-center">
                    <h3 class="font-headline text-xs font-bold text-white uppercase tracking-widest" x-text="transactionType === 'deposit' ? 'Record Deposit' : 'Record Withdrawal'"></h3>
                    <button @click="showDepositModal = false" class="text-on-surface-variant hover:text-white transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <form action="{{ route('statics.treasury.store', $static) }}" method="POST" class="p-6 space-y-4">
                    @csrf
                    <input type="hidden" name="type" :value="transactionType">

                    <div class="space-y-1">
                        <label for="user_id" class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Select Member</label>
                        <select name="user_id" id="user_id" required
                                class="w-full bg-surface-container-highest border border-white/5 rounded-sm px-3 py-2 text-sm text-white focus:ring-1 focus:ring-primary focus:border-transparent outline-none">
                            <option value="">Select a member...</option>
                            @foreach($static->members as $member)
                                <option value="{{ $member->id }}">{{ $member->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label for="amount" class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Amount (Gold)</label>
                        <input type="number" name="amount" id="amount" required placeholder="e.g., 50000"
                            class="w-full bg-surface-container-highest border border-white/5 rounded-sm px-3 py-2 text-sm text-white focus:ring-1 focus:ring-primary focus:border-transparent outline-none">
                    </div>

                    <div class="space-y-1">
                        <label for="description" class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Description</label>
                        <textarea name="description" id="description" rows="2" placeholder="Optional notes..."
                            class="w-full bg-surface-container-highest border border-white/5 rounded-sm px-3 py-2 text-sm text-white focus:ring-1 focus:ring-primary focus:border-transparent outline-none"></textarea>
                    </div>

                    <div class="pt-4">
                        <button type="submit" class="w-full bg-primary text-on-primary py-3 rounded-sm font-headline text-xs font-bold uppercase tracking-[0.2em] hover:brightness-110 active:scale-95 transition-all">
                            Save Transaction
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
