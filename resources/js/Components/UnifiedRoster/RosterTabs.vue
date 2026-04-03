<script setup>
import { computed } from 'vue';

const props = defineProps({
    activeTab: { type: String, required: true },
    selectedDifficulty: { type: String, required: true },
});

const emit = defineEmits(['update:activeTab', 'update:selectedDifficulty']);

const tabs = [
    { id: 'summary', label: 'Summary',      icon: 'dashboard' },
    { id: 'raids',   label: 'Raids',        icon: 'swords'    },
    { id: 'gear',    label: 'Gear & Audit', icon: 'shield'    },
];

const difficulties = [
    { key: 'M',   label: 'Mythic', activeClass: 'bg-orange-500 text-white shadow-sm shadow-orange-500/40' },
    { key: 'H',   label: 'Heroic', activeClass: 'bg-purple-500 text-white shadow-sm shadow-purple-500/40' },
    { key: 'N',   label: 'Normal', activeClass: 'bg-blue-500   text-white shadow-sm shadow-blue-500/40'   },
    { key: 'LFR', label: 'LFR',    activeClass: 'bg-green-600  text-white shadow-sm shadow-green-600/40'  },
];
</script>

<template>
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <!-- ── Tabs ─────────────────────────────────────────────────────── -->
        <div class="flex items-center gap-1 bg-surface-container-high p-1 rounded-xl border border-white/5 w-fit">
            <button v-for="tab in tabs" :key="tab.id"
                    @click="emit('update:activeTab', tab.id)"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-wider transition-all"
                    :class="activeTab === tab.id
                        ? 'bg-primary text-white shadow-lg'
                        : 'text-on-surface-variant hover:bg-white/5 hover:text-white'">
                <span class="material-symbols-outlined text-sm">{{ tab.icon }}</span>
                {{ tab.label }}
            </button>
        </div>

        <!-- ── Difficulty toggle (Raids tab only) ───────────────── -->
        <div v-if="activeTab === 'raids'" class="flex items-center gap-3">
            <span class="text-[10px] text-on-surface-variant font-bold uppercase tracking-widest">
                Viewing:
            </span>
            <div class="flex gap-1 bg-black/20 p-1 rounded-lg border border-white/5">
                <button v-for="diff in difficulties" :key="diff.key"
                        @click="emit('update:selectedDifficulty', diff.key)"
                        class="px-3 py-1 rounded text-[10px] font-black uppercase tracking-wider transition-all"
                        :class="selectedDifficulty === diff.key
                            ? diff.activeClass
                            : 'text-on-surface-variant hover:text-white hover:bg-white/5'">
                    {{ diff.label }}
                </button>
            </div>
        </div>
    </div>
</template>
