<script setup>
import {computed} from 'vue';

const props = defineProps({
    activeTab: { type: String, required: true },
    selectedDifficulty: { type: String, required: true },
    canManageStatus: { type: Boolean, default: false },
});

const emit = defineEmits(['update:activeTab', 'update:selectedDifficulty']);

const tabs = computed(() => {
    return [
        {id: 'summary', labelKey: 'Summary', icon: 'dashboard'},
        {id: 'raids', labelKey: 'Raids', icon: 'swords'},
        {id: 'gear', labelKey: 'Спорядження', icon: 'shield'},
        {id: 'vault', labelKey: 'Vault', icon: 'inventory_2'},
    ];
});

const difficulties = [
    { key: 'M',   labelKey: 'Mythic', activeClass: 'bg-orange-500 text-white shadow-sm shadow-orange-500/40' },
    { key: 'H',   labelKey: 'Heroic', activeClass: 'bg-purple-500 text-white shadow-sm shadow-purple-500/40' },
    { key: 'N',   labelKey: 'Normal', activeClass: 'bg-blue-500   text-white shadow-sm shadow-blue-500/40'   },
    { key: 'LFR', labelKey: 'LFR',    activeClass: 'bg-green-600  text-white shadow-sm shadow-green-600/40'  },
];
</script>

<template>
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <!-- ── Tabs ─────────────────────────────────────────────────────── -->
        <div class="flex items-center gap-1.5">
            <button v-for="tab in tabs" :key="tab.id"
                    @click="emit('update:activeTab', tab.id)"
                    class="flex items-center gap-2 px-3.5 py-2 rounded-lg text-2xs font-black uppercase tracking-[0.06em] transition-all"
                    :style="activeTab === tab.id
                        ? { background: 'rgba(57,255,20,0.12)', border: '1px solid rgba(57,255,20,0.5)', color: '#39FF14' }
                        : { background: 'transparent', border: '1px solid rgba(255,255,255,0.06)', color: '#adaaad' }">
                <span class="material-symbols-outlined text-sm">{{ tab.icon }}</span>
                {{ __(tab.labelKey) }}
            </button>
        </div>

        <!-- ── Difficulty toggle (Raids tab only) ───────────────── -->
        <div v-if="activeTab === 'raids'" class="flex items-center gap-3">
            <span class="text-3xs text-on-surface-variant font-semibold uppercase tracking-wider">
                {{ __('Viewing:') }}
            </span>
            <div class="flex gap-1 bg-black/20 p-1 rounded-lg border border-white/5">
                <button v-for="diff in difficulties" :key="diff.key"
                        @click="emit('update:selectedDifficulty', diff.key)"
                        class="px-3 py-1 rounded text-3xs font-bold uppercase tracking-wider transition-all"
                        :class="selectedDifficulty === diff.key
                            ? diff.activeClass
                            : 'text-on-surface-variant hover:text-white hover:bg-white/5'">
                    {{ __(diff.labelKey) }}
                </button>
            </div>
        </div>
    </div>
</template>
