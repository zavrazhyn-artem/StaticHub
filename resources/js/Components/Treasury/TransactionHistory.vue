<template>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <a :href="treasuryUrl" class="text-on-surface-variant hover:text-white transition-colors">
                        <span class="material-symbols-outlined text-xl">arrow_back</span>
                    </a>
                    <h1 class="text-4xl font-black text-white uppercase tracking-tighter font-headline">{{ __('Transaction History') }}</h1>
                </div>
                <p class="text-on-surface-variant font-medium mt-1 uppercase tracking-widest text-xs ml-9">
                    {{ staticName }} &bull; {{ __('Full Ledger') }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <div class="w-64">
                <SelectUserWithMain
                    v-model="selectedMember"
                    :members="members"
                    :placeholder="__('Filter by member...')"
                    :search-placeholder="__('Search...')"
                    @update:model-value="applyFilter"
                />
            </div>
        </div>

        <div class="bg-surface-container border border-white/5 rounded-xl shadow-2xl backdrop-blur-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-container-highest border-b border-white/5">
                            <th class="px-4 py-3 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest font-headline">{{ __('Date') }}</th>
                            <th class="px-4 py-3 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest font-headline">{{ __('Member') }}</th>
                            <th class="px-4 py-3 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest font-headline">{{ __('Type') }}</th>
                            <th class="px-4 py-3 text-right text-[10px] font-bold text-on-surface-variant uppercase tracking-widest font-headline">{{ __('Amount') }}</th>
                            <th class="px-4 py-3 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest font-headline">{{ __('Description') }}</th>
                            <th v-if="canManageTreasury" class="px-4 py-3 text-center text-[10px] font-bold text-on-surface-variant uppercase tracking-widest font-headline w-10"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <tr v-for="tx in transactions" :key="tx.id" class="hover:bg-white/5 transition-colors">
                            <td class="px-4 py-4 text-[10px] text-on-surface-variant font-medium whitespace-nowrap">
                                {{ formatDate(tx.created_at) }}
                            </td>
                            <td class="px-4 py-4">
                                <span class="text-xs font-bold"
                                      :style="{ color: getClassTextColor(tx.playable_class) }">
                                    {{ tx.display_name }}
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                <span
                                    class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-widest px-2 py-0.5 rounded-sm"
                                    :class="tx.type === 'deposit' ? 'bg-success-neon/10 text-success-neon' : 'bg-error/10 text-error'"
                                >
                                    <span class="material-symbols-outlined text-xs">{{ tx.type === 'deposit' ? 'arrow_downward' : 'arrow_upward' }}</span>
                                    {{ tx.type === 'deposit' ? __('Deposit') : __('Withdrawal') }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-right">
                                <span class="text-sm font-black font-headline tracking-tight" :class="tx.type === 'deposit' ? 'text-[#FFD700]' : 'text-error'">
                                    {{ tx.type === 'deposit' ? '+' : '-' }}{{ formatGold(tx.amount) }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-xs text-on-surface-variant max-w-[200px] truncate">
                                {{ tx.description || '—' }}
                            </td>
                            <td v-if="canManageTreasury" class="px-4 py-4 text-center">
                                <button @click="openEditModal(tx)" class="text-on-surface-variant hover:text-yellow-500 transition-colors">
                                    <span class="material-symbols-outlined text-lg">{{ tx.description ? 'edit_note' : 'add_comment' }}</span>
                                </button>
                            </td>
                        </tr>
                        <tr v-if="transactions.length === 0">
                            <td :colspan="canManageTreasury ? 6 : 5" class="px-6 py-12 text-center text-on-surface-variant italic text-sm">
                                {{ __('No transactions found.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <TransactionCommentModal
            :show="showEditModal"
            :transaction="editingTransaction"
            :static-id="staticId"
            :csrf-token="csrfToken"
            @close="showEditModal = false"
        />
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
import { useWowClasses } from '@/composables/useWowClasses';
import SelectUserWithMain from '@/Components/UI/SelectUserWithMain.vue';
import TransactionCommentModal from './TransactionCommentModal.vue';

const { __ } = useTranslation();
const { getClassTextColor } = useWowClasses();

const props = defineProps({
    staticId: { type: Number, required: true },
    staticName: { type: String, required: true },
    canManageTreasury: { type: Boolean, default: false },
    transactions: { type: Array, required: true },
    members: { type: Array, required: true },
    selectedUserId: { type: String, default: '' },
    filterUrl: { type: String, required: true },
    treasuryUrl: { type: String, required: true },
    csrfToken: { type: String, required: true },
});

const selectedMember = ref(props.selectedUserId);
const showEditModal = ref(false);
const editingTransaction = ref({ id: null, description: '', member: '', date: '', amount: '', type: '' });

const applyFilter = (value) => {
    const url = new URL(props.filterUrl, window.location.origin);
    if (value) {
        url.searchParams.set('member', value);
    }
    window.location.href = url.toString();
};

const formatGold = (copper) => {
    return Math.floor(copper / 10000).toLocaleString();
};

const formatDate = (dateString) => {
    const date = new Date(dateString);
    const options = { year: 'numeric', month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false };
    return date.toLocaleDateString('en-US', options).replace(',', '');
};

const openEditModal = (tx) => {
    editingTransaction.value = {
        id: tx.id,
        description: tx.description || '',
        member: tx.display_name,
        date: formatDate(tx.created_at),
        amount: formatGold(tx.amount),
        type: tx.type,
    };
    showEditModal.value = true;
};
</script>
