<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    encounters: { type: Array, required: true },
    encounterRosters: { type: Object, default: () => ({}) },
    selectedEncounter: { type: String, default: null },
    canManage: { type: Boolean, default: false },
    difficulty: { type: String, default: 'mythic' },
});

const emit = defineEmits(['select', 'reorder']);

const difficultyColors = {
    mythic: 'text-orange-400',
    heroic: 'text-purple-400',
    normal: 'text-green-400',
    raid_finder: 'text-blue-400',
};

const getSelectedCount = (slug) => {
    const roster = props.encounterRosters[slug];
    if (!roster) return 0;
    return (roster.selected?.length || 0);
};

const isActive = (slug) => props.selectedEncounter === slug;

const bossImageSlug = (name) => {
    return name.toLowerCase()
        .replace(/['']/g, '')
        .replace(/&/g, '')
        .replace(/,/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
};
</script>

<template>
    <div class="flex flex-col gap-1">
        <!-- All encounters button -->
        <button
            @click="emit('select', null)"
            class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all text-left w-full"
            :class="!selectedEncounter
                ? 'bg-primary/10 border border-primary/30 text-primary'
                : 'hover:bg-white/5 text-on-surface-variant hover:text-white border border-transparent'"
        >
            <span class="material-symbols-outlined text-lg">groups</span>
            <span class="text-xs font-black uppercase tracking-widest">All Encounters</span>
        </button>

        <div class="border-t border-white/5 my-1"></div>

        <!-- Boss list -->
        <div
            v-for="(encounter, index) in encounters"
            :key="encounter.slug"
        >
            <!-- Instance header -->
            <div
                v-if="index === 0 || encounter.instance !== encounters[index - 1]?.instance"
                class="px-3 pt-3 pb-1"
            >
                <span class="text-[8px] font-black uppercase tracking-[0.2em] text-on-surface-variant/60">
                    {{ encounter.instance }}
                </span>
            </div>

            <button
                @click="emit('select', encounter.slug)"
                class="flex items-center gap-3 px-3 py-2 rounded-xl transition-all text-left w-full group"
                :class="isActive(encounter.slug)
                    ? 'bg-white/10 border border-white/20 text-white'
                    : 'hover:bg-white/5 text-on-surface-variant hover:text-white border border-transparent'"
            >
                <div class="w-8 h-8 rounded-lg overflow-hidden shrink-0 border border-white/10">
                    <img
                        v-if="encounter.portrait"
                        :src="encounter.portrait"
                        :alt="encounter.name"
                        class="w-full h-full object-cover"
                        :class="isActive(encounter.slug) ? '' : 'opacity-60 grayscale group-hover:opacity-100 group-hover:grayscale-0 transition-all'"
                    >
                    <div v-else class="w-full h-full bg-surface-container-high flex items-center justify-center">
                        <span class="material-symbols-outlined text-sm opacity-40">swords</span>
                    </div>
                </div>

                <div class="flex-1 min-w-0">
                    <div class="text-[11px] font-bold leading-tight truncate">{{ encounter.name }}</div>
                    <div class="flex items-center gap-1.5 mt-0.5">
                        <span
                            v-if="getSelectedCount(encounter.slug) > 0"
                            class="text-[8px] font-black text-primary"
                        >{{ getSelectedCount(encounter.slug) }} selected</span>
                        <span v-else class="text-[8px] text-on-surface-variant/40">No assignments</span>
                    </div>
                </div>

                <div
                    v-if="getSelectedCount(encounter.slug) > 0"
                    class="w-5 h-5 rounded-full bg-primary/20 text-primary flex items-center justify-center shrink-0"
                >
                    <span class="text-[9px] font-black">{{ getSelectedCount(encounter.slug) }}</span>
                </div>
            </button>
        </div>
    </div>
</template>
