<template>
    <div class="space-y-6">
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <h1 class="text-4xl font-black text-white uppercase tracking-tighter font-headline">{{ __('Guild Treasury') }}</h1>
                <p class="text-on-surface-variant font-medium mt-1 uppercase tracking-widest text-xs">
                    {{ staticName }} • {{ __('Financial Ledger') }}
                </p>
            </div>

            <div v-if="canManageTreasury" class="flex items-center gap-2">
                <button
                    @click="openTransactionModal('deposit')"
                    class="bg-yellow-500 text-black hover:brightness-110 px-4 py-2 rounded-sm font-headline text-[10px] font-bold uppercase tracking-widest transition-all flex items-center gap-2"
                >
                    <span class="material-symbols-outlined text-sm">add_circle</span>
                    {{ __('Record Deposit') }}
                </button>
                <button
                    @click="openTransactionModal('withdrawal')"
                    class="bg-surface-container-high text-on-surface-variant hover:text-white px-4 py-2 rounded-sm font-headline text-[10px] font-bold uppercase tracking-widest transition-colors flex items-center gap-2"
                >
                    <span class="material-symbols-outlined text-sm">remove_circle</span>
                    {{ __('Record Withdrawal') }}
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-surface-container-high border border-white/5 rounded-xl p-6 shadow-2xl backdrop-blur-sm relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                    <span class="material-symbols-outlined text-6xl text-yellow-500">savings</span>
                </div>
                <h3 class="text-on-surface-variant font-headline text-[10px] font-bold uppercase tracking-widest mb-4">{{ __('Total Reserves') }}</h3>
                <div class="flex items-baseline gap-2">
                    <span class="text-4xl font-black text-[#FFD700] tracking-tighter font-headline">{{ formatGold(reserves) }}</span>
                    <span class="text-xs font-bold text-[#FFD700]/60 uppercase tracking-widest">{{ __('Gold') }}</span>
                </div>
            </div>

            <div class="bg-surface-container-high border border-white/5 rounded-xl p-6 shadow-2xl backdrop-blur-sm relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                    <span class="material-symbols-outlined text-6xl text-yellow-500">payments</span>
                </div>

                <h3 class="text-on-surface-variant font-headline text-[10px] font-bold uppercase tracking-widest mb-4">{{ __('Required Weekly Tax') }}</h3>

                <div class="flex items-baseline gap-2">
                    <span class="text-4xl font-black text-white tracking-tighter font-headline">
                      {{ formatGold(dynamicTargetTax) }}
                    </span>
                    <span class="text-xs font-bold text-on-surface-variant uppercase tracking-widest">{{ __('Gold / Player') }}</span>
                </div>

                <div class="mt-2 text-[10px] uppercase font-bold tracking-widest transition-colors" :class="taxClass">
                    {{ taxDescription }}
                </div>
                <button v-if="canManageTreasury" @click="showTaxModal = true" class="absolute bottom-4 right-4 z-10 text-on-surface-variant hover:text-[#FFD700] transition-colors" :title="__('Edit Weekly Tax')">
                    <span class="material-symbols-outlined text-lg">settings</span>
                </button>
            </div>

            <div class="bg-surface-container-high border border-white/5 rounded-xl p-6 shadow-2xl backdrop-blur-sm relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                    <span class="material-symbols-outlined text-6xl text-yellow-500">analytics</span>
                </div>
                <h3 class="text-on-surface-variant font-headline text-[10px] font-bold uppercase tracking-widest mb-4">{{ __('Financial Health') }}</h3>
                <div class="flex items-baseline gap-2">
          <span class="text-4xl font-black tracking-tighter font-headline" :class="autonomyClass">
            {{ autonomyValue }}
          </span>
                    <span class="text-xs font-bold text-on-surface-variant uppercase tracking-widest">{{ __('Weeks of Autonomy') }}</span>
                </div>
                <div class="mt-2 text-[10px] text-on-surface-variant uppercase font-bold tracking-widest">
                    {{ __('Weekly Cost:') }} <span>{{ formatGold(dynamicWeeklyCost) }}</span> G
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-stretch">
            <div class="flex flex-col h-full">
                <slot name="planner"></slot>
            </div>

            <div class="bg-surface-container border border-white/5 rounded-xl shadow-2xl backdrop-blur-sm flex flex-col overflow-hidden">
                <div class="px-6 py-4 border-b border-white/5 bg-surface-container-high shrink-0">
                    <h3 class="font-headline text-xs font-bold text-white uppercase tracking-widest">{{ __('Weekly Tax Tracking') }}</h3>
                </div>
                <div class="p-4 space-y-2 overflow-y-auto custom-scrollbar max-h-[468px]">
                    <div v-for="status in weeklyStatus" :key="status.user_id" class="flex items-center justify-between p-3 rounded-sm bg-surface-container-lowest border border-white/5">
                        <div class="flex items-center gap-3">
                            <div class="w-2 h-2 rounded-full" :class="status.is_paid ? 'bg-success-neon shadow-[0_0_8px_rgba(0,255,153,0.5)]' : 'bg-error shadow-[0_0_8px_rgba(255,68,68,0.5)]'"></div>
                            <span class="text-sm font-medium"
                                  :style="{ color: getClassTextColor(status.playable_class) }">
                                {{ status.display_name }}
                            </span>
                        </div>
                        <div class="text-right">
                            <span class="text-xs font-bold" :class="status.is_paid ? 'text-success-neon' : 'text-error'">
                                {{ status.is_paid ? __('Covered') : __('Not Covered') }}
                            </span>
                            <div class="text-[9px] text-on-surface-variant font-medium">
                                {{ __('Balance:') }} {{ formatGold(status.balance) }}g
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-surface-container border border-white/5 rounded-xl shadow-2xl backdrop-blur-sm flex flex-col overflow-hidden">
                <div class="px-6 py-4 border-b border-white/5 bg-surface-container-high flex justify-between items-center shrink-0">
                    <h3 class="font-headline text-xs font-bold text-white uppercase tracking-widest">{{ __('Recent Transactions') }}</h3>
                    <a :href="`/statics/${staticId}/treasury/history`" class="text-[10px] font-bold text-yellow-500 hover:text-white uppercase tracking-widest transition-colors flex items-center gap-1">
                        {{ __('View All') }}
                        <span class="material-symbols-outlined text-sm">arrow_forward</span>
                    </a>
                </div>
                <div class="overflow-x-auto overflow-y-auto custom-scrollbar max-h-[348px]">
                    <table class="w-full text-left border-collapse">
                        <thead>
                        <tr class="bg-surface-container-highest border-b border-white/5">
                            <th class="px-4 py-3 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest font-headline">{{ __('Date') }}</th>
                            <th class="px-4 py-3 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest font-headline">{{ __('Member') }}</th>
                            <th class="px-4 py-3 text-right text-[10px] font-bold text-on-surface-variant uppercase tracking-widest font-headline">{{ __('Amount') }}</th>
                            <th class="px-4 py-3 text-center text-[10px] font-bold text-on-surface-variant uppercase tracking-widest font-headline w-10"></th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                        <tr v-for="tx in recentTransactions" :key="tx.id" class="hover:bg-white/5 transition-colors group">
                            <td class="px-4 py-4 text-[10px] text-on-surface-variant font-medium">
                                {{ formatDate(tx.created_at) }}
                            </td>
                            <td class="px-4 py-4">
                                <span class="text-xs font-bold"
                                      :style="{ color: getClassTextColor(tx.playable_class) }">
                                    {{ tx.display_name }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-right">
                  <span class="text-sm font-black font-headline tracking-tight" :class="tx.type === 'deposit' ? 'text-[#FFD700]' : 'text-error'">
                    {{ tx.type === 'deposit' ? '+' : '-' }}{{ formatGold(tx.amount) }}
                  </span>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <button @click="openEditModal(tx)" class="text-on-surface-variant hover:text-yellow-500 transition-colors flex items-center justify-center w-full">
                                    <span class="material-symbols-outlined text-lg">{{ tx.description ? 'chat_bubble' : 'add_comment' }}</span>
                                </button>
                            </td>
                        </tr>
                        <tr v-if="recentTransactions.length === 0">
                            <td colspan="4" class="px-6 py-12 text-center text-on-surface-variant italic text-sm">
                                {{ __('No transactions recorded yet.') }}
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Weekly Tax Modal (leader/officer only) -->
        <GlassModal v-if="canManageTreasury" :show="showTaxModal" @close="closeTaxModal">
            <div class="px-6 py-4 border-b border-white/5 flex justify-between items-center">
                <h3 class="font-headline text-xs font-bold text-[#FFD700] uppercase tracking-widest">{{ __('Edit Weekly Tax') }}</h3>
                <button @click="closeTaxModal" class="text-on-surface-variant hover:text-white transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div class="space-y-1">
                    <label for="tax_amount" class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('Weekly Tax Per Player (Gold)') }}</label>
                    <div class="relative group">
                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <span class="material-symbols-outlined text-on-surface-variant group-focus-within:text-[#FFD700] transition-colors text-lg">payments</span>
                        </span>
                        <input type="number" id="tax_amount" v-model.number="taxInputValue" min="0"
                               class="w-full pl-12 pr-4 py-3 bg-surface-container-highest border border-white/5 rounded-sm font-headline text-sm font-bold text-white tracking-widest focus:ring-2 focus:ring-[#FFD700] focus:border-transparent transition-all outline-none">
                    </div>
                    <p class="text-[9px] text-on-surface-variant font-medium uppercase tracking-wider">{{ __('Amount of gold each raider is expected to contribute weekly.') }}</p>
                </div>
                <div v-if="taxSaveError" class="text-[10px] text-error font-bold uppercase tracking-wider">{{ taxSaveError }}</div>
                <div class="pt-2">
                    <button @click="saveTax" :disabled="taxSaving"
                            class="w-full bg-yellow-500 text-black py-3 rounded-sm font-headline text-xs font-bold uppercase tracking-[0.2em] hover:brightness-110 active:scale-95 transition-all disabled:opacity-50 flex items-center justify-center gap-2">
                        <span v-if="taxSaving" class="material-symbols-outlined text-lg animate-spin">sync</span>
                        <span v-else class="material-symbols-outlined text-lg">save</span>
                        {{ taxSaving ? __('Saving...') : __('Save') }}
                    </button>
                </div>
            </div>
        </GlassModal>

        <TransactionCommentModal
            :show="showEditModal"
            :transaction="editingTransaction"
            :static-id="staticId"
            :csrf-token="csrfToken"
            @close="showEditModal = false"
        />

        <TransactionFormModal
            :show="showDepositModal"
            :transaction-type="transactionType"
            :members="members"
            :static-id="staticId"
            :csrf-token="csrfToken"
            @close="showDepositModal = false"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
import { useWowClasses } from '@/composables/useWowClasses';
import GlassModal from '@/Components/UI/GlassModal.vue';
import TransactionCommentModal from './TransactionCommentModal.vue';
import TransactionFormModal from './TransactionFormModal.vue';
const { __ } = useTranslation();
const { getClassTextColor } = useWowClasses();

const props = defineProps({
    staticId: { type: Number, required: true },
    staticName: { type: String, required: true },
    canManageTreasury: { type: Boolean, default: false },
    initialTargetTax: { type: Number, required: true },
    initialWeeklyCost: { type: Number, required: true },
    initialTaxStatus: { type: String, required: true },
    initialTaxDescription: { type: String, required: true },
    initialTaxClass: { type: String, required: true },
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
const taxDescription = ref(props.initialTaxDescription);
const taxClass = ref(props.initialTaxClass);

const showTaxModal = ref(false);
const taxInputValue = ref(Math.floor(props.initialTargetTax / 10000));
const taxSaving = ref(false);
const taxSaveError = ref('');

const closeTaxModal = () => {
    showTaxModal.value = false;
    taxSaveError.value = '';
};

const saveTax = async () => {
    taxSaving.value = true;
    taxSaveError.value = '';
    try {
        const response = await fetch(`/statics/${props.staticId}/treasury-settings`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': props.csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ weekly_tax_per_player: taxInputValue.value }),
        });
        if (!response.ok) throw new Error('Failed');
        const data = await response.json();
        dynamicTargetTax.value = data.targetTax;
        taxDescription.value = data.taxDescription;
        taxClass.value = data.taxClass;
        closeTaxModal();
    } catch {
        taxSaveError.value = __('Failed to save. Please try again.');
    } finally {
        taxSaving.value = false;
    }
};

const formatGold = (copper) => {
    return Math.floor(copper / 10000).toLocaleString();
};

const formatDate = (dateString) => {
    const date = new Date(dateString);
    const options = { month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false };
    return date.toLocaleDateString('en-US', options).replace(',', '');
};

const handleConsumablesUpdated = (event) => {
    const { taxPerRaider, totalCost, taxDescription: desc, taxClass: cls } = event.detail;
    dynamicWeeklyCost.value = totalCost;
    taxDescription.value = desc;
    taxClass.value = cls;
};

const openTransactionModal = (type) => {
    transactionType.value = type;
    showDepositModal.value = true;
};

const openEditModal = (tx) => {
    editingTransaction.value = {
        id: tx.id,
        description: tx.description || '',
        member: tx.display_name,
        date: formatDate(tx.created_at),
        amount: formatGold(tx.amount),
        type: tx.type
    };
    showEditModal.value = true;
};

const autonomyValue = computed(() => {
    if (dynamicWeeklyCost.value <= 0) return '∞';
    return (props.reserves / dynamicWeeklyCost.value).toFixed(1);
});

const autonomyClass = computed(() => {
    if (dynamicWeeklyCost.value <= 0) return 'text-success-neon';
    return (props.reserves / dynamicWeeklyCost.value) > 2 ? 'text-success-neon' : 'text-error';
});

onMounted(() => {
    window.addEventListener('consumables-updated', handleConsumablesUpdated);
});

onUnmounted(() => {
    window.removeEventListener('consumables-updated', handleConsumablesUpdated);
});
</script>

<style scoped>
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
