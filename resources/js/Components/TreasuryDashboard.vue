<template>
    <div class="space-y-6">
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <h1 class="text-4xl font-black text-white uppercase tracking-tighter font-headline">Guild Treasury</h1>
                <p class="text-on-surface-variant font-medium mt-1 uppercase tracking-widest text-xs">
                    {{ staticName }} • Financial Ledger
                </p>
            </div>

            <div class="flex items-center gap-2">
                <button
                    @click="openTransactionModal('deposit')"
                    class="bg-primary text-on-primary hover:brightness-110 px-4 py-2 rounded-sm font-headline text-[10px] font-bold uppercase tracking-widest transition-all flex items-center gap-2"
                >
                    <span class="material-symbols-outlined text-sm">add_circle</span>
                    Record Deposit
                </button>
                <button
                    @click="openTransactionModal('withdrawal')"
                    class="bg-surface-container-high text-on-surface-variant hover:text-white px-4 py-2 rounded-sm font-headline text-[10px] font-bold uppercase tracking-widest transition-colors flex items-center gap-2"
                >
                    <span class="material-symbols-outlined text-sm">remove_circle</span>
                    Record Withdrawal
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-surface-container-high border border-white/5 rounded-xl p-6 shadow-2xl backdrop-blur-sm relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                    <span class="material-symbols-outlined text-6xl text-primary">savings</span>
                </div>
                <h3 class="text-on-surface-variant font-headline text-[10px] font-bold uppercase tracking-widest mb-4">Total Reserves</h3>
                <div class="flex items-baseline gap-2">
                    <span class="text-4xl font-black text-[#FFD700] tracking-tighter font-headline">{{ formatGold(reserves) }}</span>
                    <span class="text-xs font-bold text-[#FFD700]/60 uppercase tracking-widest">Gold</span>
                </div>
            </div>

            <div class="bg-surface-container-high border border-white/5 rounded-xl p-6 shadow-2xl backdrop-blur-sm relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                    <span class="material-symbols-outlined text-6xl text-primary">payments</span>
                </div>

                <h3 class="text-on-surface-variant font-headline text-[10px] font-bold uppercase tracking-widest mb-4">Required Weekly Tax</h3>

                <div class="flex items-baseline gap-2">
                    <span class="text-4xl font-black text-white tracking-tighter font-headline">
                      {{ formatGold(initialTargetTax) }}
                    </span>
                    <span class="text-xs font-bold text-on-surface-variant uppercase tracking-widest">Gold / Player</span>
                </div>

                <div class="mt-2 text-[10px] uppercase font-bold tracking-widest transition-colors" :class="getTaxStatusClass()">
                    {{ getTaxStatusText() }}
                </div>
            </div>

            <div class="bg-surface-container-high border border-white/5 rounded-xl p-6 shadow-2xl backdrop-blur-sm relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                    <span class="material-symbols-outlined text-6xl text-primary">analytics</span>
                </div>
                <h3 class="text-on-surface-variant font-headline text-[10px] font-bold uppercase tracking-widest mb-4">Financial Health</h3>
                <div class="flex items-baseline gap-2">
          <span class="text-4xl font-black tracking-tighter font-headline" :class="getAutonomyClass()">
            {{ getAutonomyValue() }}
          </span>
                    <span class="text-xs font-bold text-on-surface-variant uppercase tracking-widest">Weeks of Autonomy</span>
                </div>
                <div class="mt-2 text-[10px] text-on-surface-variant uppercase font-bold tracking-widest">
                    Weekly Cost: <span>{{ formatGold(dynamicWeeklyCost) }}</span> G
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-stretch">
            <div class="flex flex-col h-full">
                <slot name="planner"></slot>
            </div>

            <div class="bg-surface-container border border-white/5 rounded-xl shadow-2xl backdrop-blur-sm flex flex-col h-full overflow-hidden">
                <div class="px-6 py-4 border-b border-white/5 bg-surface-container-high shrink-0">
                    <h3 class="font-headline text-xs font-bold text-white uppercase tracking-widest">Weekly Tax Tracking</h3>
                </div>
                <div class="p-4 space-y-2 overflow-y-auto flex-1 custom-scrollbar">
                    <div v-for="status in weeklyStatus" :key="status.name" class="flex items-center justify-between p-3 rounded-sm bg-surface-container-lowest border border-white/5">
                        <div class="flex items-center gap-3">
                            <div class="w-2 h-2 rounded-full" :class="status.is_paid ? 'bg-success-neon shadow-[0_0_8px_rgba(0,255,153,0.5)]' : 'bg-error shadow-[0_0_8px_rgba(255,68,68,0.5)]'"></div>
                            <span class="text-sm font-medium text-white">{{ status.name }}</span>
                        </div>
                        <div class="text-right">
                            <div class="text-xs font-bold" :class="status.is_paid ? 'text-success-neon' : 'text-on-surface-variant'">
                                {{ formatGold(status.total_paid) }} / {{ formatGold(initialTargetTax) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-surface-container border border-white/5 rounded-xl shadow-2xl backdrop-blur-sm flex flex-col h-full overflow-hidden">
                <div class="px-6 py-4 border-b border-white/5 bg-surface-container-high flex justify-between items-center shrink-0">
                    <h3 class="font-headline text-xs font-bold text-white uppercase tracking-widest">Recent Transactions</h3>
                </div>
                <div class="overflow-x-auto overflow-y-auto flex-1 custom-scrollbar">
                    <table class="w-full text-left border-collapse">
                        <thead>
                        <tr class="bg-surface-container-highest border-b border-white/5">
                            <th class="px-4 py-3 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest font-headline">Date</th>
                            <th class="px-4 py-3 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest font-headline">Member</th>
                            <th class="px-4 py-3 text-right text-[10px] font-bold text-on-surface-variant uppercase tracking-widest font-headline">Amount</th>
                            <th class="px-4 py-3 text-center text-[10px] font-bold text-on-surface-variant uppercase tracking-widest font-headline w-10"></th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                        <tr v-for="tx in recentTransactions" :key="tx.id" class="hover:bg-white/5 transition-colors group">
                            <td class="px-4 py-4 text-[10px] text-on-surface-variant font-medium">
                                {{ formatDate(tx.created_at) }}
                            </td>
                            <td class="px-4 py-4">
                                <span class="text-xs font-bold text-white">{{ tx.user.name }}</span>
                            </td>
                            <td class="px-4 py-4 text-right">
                  <span class="text-sm font-black font-headline tracking-tight" :class="tx.type === 'deposit' ? 'text-[#FFD700]' : 'text-error'">
                    {{ tx.type === 'deposit' ? '+' : '-' }}{{ formatGold(tx.amount) }}
                  </span>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <button @click="openEditModal(tx)" class="text-on-surface-variant hover:text-primary transition-colors flex items-center justify-center w-full">
                                    <span class="material-symbols-outlined text-lg">{{ tx.description ? 'chat_bubble' : 'add_comment' }}</span>
                                </button>
                            </td>
                        </tr>
                        <tr v-if="recentTransactions.length === 0">
                            <td colspan="4" class="px-6 py-12 text-center text-on-surface-variant italic text-sm">
                                No transactions recorded yet.
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-if="showEditModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm ">
            <div @click.stop class="w-full max-w-md bg-surface-container border border-white/10 rounded-xl shadow-2xl overflow-hidden glassmorphism">
                <div class="px-6 py-4 border-b border-white/5 flex justify-between items-center">
                    <h3 class="font-headline text-xs font-bold text-white uppercase tracking-widest">Transaction Comment</h3>
                    <button @click="showEditModal = false" class="text-on-surface-variant hover:text-white transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <form :action="`/statics/${staticId}/treasury/${editingTransaction.id}`" method="POST" class="p-6 space-y-4">
                    <input type="hidden" name="_token" :value="csrfToken">
                    <input type="hidden" name="_method" value="PATCH">

                    <div class="p-3 rounded-lg bg-surface-container-highest border border-white/5 space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">{{ editingTransaction.date }}</span>
                            <span class="text-sm font-black font-headline tracking-tight" :class="editingTransaction.type === 'deposit' ? 'text-[#FFD700]' : 'text-error'">
                {{ (editingTransaction.type === 'deposit' ? '+' : '-') + editingTransaction.amount }}
              </span>
                        </div>
                        <div class="text-xs font-bold text-white">{{ editingTransaction.member }}</div>
                    </div>

                    <div class="space-y-1">
                        <label for="edit_description" class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Comment</label>
                        <textarea name="description" id="edit_description" rows="4" v-model="editingTransaction.description" placeholder="Optional notes..."
                                  class="w-full bg-surface-container-highest border border-white/5 rounded-sm px-3 py-2 text-sm text-white focus:ring-1 focus:ring-primary focus:border-transparent outline-none"></textarea>
                    </div>

                    <div class="pt-4">
                        <button type="submit" class="w-full bg-primary text-on-primary py-3 rounded-sm font-headline text-xs font-bold uppercase tracking-[0.2em] hover:brightness-110 active:scale-95 transition-all">
                            Save Comment
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="showDepositModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div @click.stop class="w-full max-w-md bg-surface-container border border-white/10 rounded-xl shadow-2xl overflow-hidden glassmorphism">
                <div class="px-6 py-4 border-b border-white/5 flex justify-between items-center">
                    <h3 class="font-headline text-xs font-bold text-white uppercase tracking-widest">
                        {{ transactionType === 'deposit' ? 'Record Deposit' : 'Record Withdrawal' }}
                    </h3>
                    <button @click="showDepositModal = false" class="text-on-surface-variant hover:text-white transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <form :action="`/statics/${staticId}/treasury`" method="POST" class="p-6 space-y-4">
                    <input type="hidden" name="_token" :value="csrfToken">
                    <input type="hidden" name="type" :value="transactionType">

                    <div class="space-y-1">
                        <label for="user_id" class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Select Member</label>
                        <select name="user_id" id="user_id" required
                                class="w-full bg-surface-container-highest border border-white/5 rounded-sm px-3 py-2 text-sm text-white focus:ring-1 focus:ring-primary focus:border-transparent outline-none">
                            <option value="">Select a member...</option>
                            <option v-for="member in members" :key="member.id" :value="member.id">{{ member.name }}</option>
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
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    staticId: { type: Number, required: true },
    staticName: { type: String, required: true },
    initialTargetTax: { type: Number, required: true },
    initialWeeklyCost: { type: Number, required: true },
    initialTaxStatus: { type: String, required: true },
    initialTaxDescription: { type: String, required: true },
    reserves: { type: Number, required: true },
    members: { type: Array, required: true },
    weeklyStatus: { type: Array, required: true },
    recentTransactions: { type: Array, required: true },
    csrfToken: { type: String, required: true }
});

const showDepositModal = ref(false);
const transactionType = ref('deposit');
const showEditModal = ref(false);
const editingTransaction = ref({ id: null, description: '', member: '', date: '', amount: '', type: '' });

const dynamicTargetTax = ref(props.initialTargetTax);
const dynamicWeeklyCost = ref(props.initialWeeklyCost);

const formatGold = (copper) => {
    return Math.floor(copper / 10000).toLocaleString();
};

const formatDate = (dateString) => {
    const date = new Date(dateString);
    const options = { month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false };
    return date.toLocaleDateString('en-US', options).replace(',', '');
};

const handleConsumablesUpdated = (event) => {
    dynamicTargetTax.value = event.detail.taxPerRaider;
    dynamicWeeklyCost.value = event.detail.totalCost;
};

const openTransactionModal = (type) => {
    transactionType.value = type;
    showDepositModal.value = true;
};

const openEditModal = (tx) => {
    editingTransaction.value = {
        id: tx.id,
        description: tx.description || '',
        member: tx.user.name,
        date: formatDate(tx.created_at),
        amount: formatGold(tx.amount),
        type: tx.type
    };
    showEditModal.value = true;
};

const getTaxStatusClass = () => {
    const diff = props.initialTargetTax - dynamicTargetTax.value;

    if (dynamicTargetTax.value === props.initialTargetTax) {
        return props.initialTaxStatus === 'danger' ? 'text-error' : (props.initialTaxStatus === 'warning' ? 'text-warning' : 'text-on-surface-variant');
    }

    if (diff < 0) return 'text-error';
    if (dynamicTargetTax.value > 0 && diff > dynamicTargetTax.value * 0.5) return 'text-warning';
    return 'text-success-neon';
};

// Відновлюємо функцію для виведення правильного тексту
const getTaxStatusText = () => {
    if (dynamicTargetTax.value === props.initialTargetTax) {
        return props.initialTaxDescription;
    }

    const diff = props.initialTargetTax - dynamicTargetTax.value;

    if (diff < 0) {
        return `⚠️ DEFICIT: TAX IS ${formatGold(Math.abs(diff))}G BELOW MARKET COST!`;
    } else if (dynamicTargetTax.value > 0 && diff > dynamicTargetTax.value * 0.5) {
        return `⚠️ HIGH TAX: ${formatGold(diff)}G SURPLUS PER PLAYER`;
    } else {
        return `HEALTHY TAX`;
    }
};

const getAutonomyValue = () => {
    if (dynamicWeeklyCost.value <= 0) return '∞';
    return (props.reserves / dynamicWeeklyCost.value).toFixed(1);
};

const getAutonomyClass = () => {
    if (dynamicWeeklyCost.value <= 0) return 'text-success-neon';
    return (props.reserves / dynamicWeeklyCost.value) > 2 ? 'text-success-neon' : 'text-error';
};

onMounted(() => {
    window.addEventListener('consumables-updated', handleConsumablesUpdated);
});

onUnmounted(() => {
    window.removeEventListener('consumables-updated', handleConsumablesUpdated);
});
</script>

<style scoped>
.glassmorphism {
    background: rgba(30, 31, 35, 0.8);
    backdrop-filter: blur(10px);
}
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.2);
}
</style>
