<script setup>
import { ref, computed } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
const { __ } = useTranslation();

const props = defineProps({
    encounters: { type: Array, required: true },
    encounterRosters: { type: Object, default: () => ({}) },
    selectedEncounter: { type: String, default: null },
    selectedEncounters: { type: Array, default: null }, // null = all selected
    lockedBosses: { type: Set, default: () => new Set() },
    assignedPlans: { type: Object, default: () => ({}) },
    canManage: { type: Boolean, default: false },
    difficulty: { type: String, default: 'mythic' },
    mainRosterCount: { type: Number, default: 0 },
});

const emit = defineEmits(['select', 'reorder', 'toggle-encounter', 'toggle-all', 'toggle-lock']);

const getSelectedCount = (slug) => {
    const roster = props.encounterRosters[slug];
    if (!roster || (roster.selected?.length === 0 && roster.queued?.length === 0)) {
        // No saved roster — inherited from main, show main count
        return props.mainRosterCount;
    }
    return (roster.selected?.length || 0);
};

const hasSavedRoster = (slug) => {
    const roster = props.encounterRosters[slug];
    return roster && (roster.selected?.length > 0 || roster.queued?.length > 0);
};

const isActive = (slug) => props.selectedEncounter === slug;

const isEncounterChecked = (slug) => {
    if (!props.selectedEncounters) return true; // null = all
    return props.selectedEncounters.includes(slug);
};

const onCheckboxChange = (slug, event) => {
    event.stopPropagation();
    emit('toggle-encounter', { slug, selected: !isEncounterChecked(slug) });
};

const allChecked = computed(() => !props.selectedEncounters); // null = all
const onToggleAll = (event) => {
    event.stopPropagation();
    emit('toggle-all', !allChecked.value);
};
</script>

<template>
    <div class="flex flex-col gap-1">
        <!-- All encounters button -->
        <button
            @click="emit('select', null)"
            class="flex items-center gap-2 px-3 py-2.5 rounded-xl transition-all text-left w-full"
            :class="!selectedEncounter
                ? 'bg-fuchsia-400/10 border border-fuchsia-400/30 text-fuchsia-400'
                : 'hover:bg-white/5 text-on-surface-variant hover:text-white border border-transparent'"
        >
            <!-- Select/deselect all checkbox (managers only) -->
            <div
                v-if="canManage"
                @click.stop="onToggleAll($event)"
                class="w-4 h-4 rounded border shrink-0 flex items-center justify-center cursor-pointer transition-all"
                :class="allChecked
                    ? 'bg-fuchsia-400/80 border-fuchsia-400 text-white'
                    : 'border-white/20 hover:border-white/40'"
            >
                <span
                    v-if="allChecked"
                    class="material-symbols-outlined text-xs font-bold"
                >check</span>
            </div>
            <span class="material-symbols-outlined text-lg">groups</span>
            <span class="text-xs font-black uppercase tracking-widest">{{ __('All Encounters') }}</span>
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
                <span class="text-5xs font-black uppercase tracking-[0.2em] text-on-surface-variant/60">
                    {{ encounter.instance }}
                </span>
            </div>

            <button
                @click="emit('select', encounter.slug)"
                class="flex items-center gap-2 px-3 py-2 rounded-xl transition-all text-left w-full group"
                :class="[
                    isActive(encounter.slug)
                        ? 'bg-white/10 border border-white/20 text-white'
                        : 'hover:bg-white/5 text-on-surface-variant hover:text-white border border-transparent',
                    !isEncounterChecked(encounter.slug) ? 'opacity-40' : ''
                ]"
            >
                <!-- Checkbox (only for managers) -->
                <div
                    v-if="canManage"
                    @click.stop="onCheckboxChange(encounter.slug, $event)"
                    class="w-4 h-4 rounded border shrink-0 flex items-center justify-center cursor-pointer transition-all"
                    :class="isEncounterChecked(encounter.slug)
                        ? 'bg-fuchsia-400/80 border-fuchsia-400 text-white'
                        : 'border-white/20 hover:border-white/40'"
                >
                    <span
                        v-if="isEncounterChecked(encounter.slug)"
                        class="material-symbols-outlined text-xs font-bold"
                    >check</span>
                </div>

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
                    <div class="text-2xs font-bold leading-tight truncate flex items-center gap-1">
                        {{ encounter.name }}
                        <span v-if="assignedPlans[encounter.slug]" class="material-symbols-outlined text-2xs text-orange-400" title="Plan assigned">map</span>
                    </div>
                    <div class="flex items-center gap-1.5 mt-0.5">
                        <span
                            v-if="!isEncounterChecked(encounter.slug)"
                            class="text-5xs font-black text-on-surface-variant/50"
                        >{{ __('Skipped') }}</span>
                        <span
                            v-else-if="getSelectedCount(encounter.slug) > 0"
                            class="text-5xs font-black"
                            :class="hasSavedRoster(encounter.slug) ? 'text-fuchsia-400' : 'text-on-surface-variant/60'"
                        >{{ getSelectedCount(encounter.slug) }} {{ hasSavedRoster(encounter.slug) ? __('selected') : __('inherited') }}</span>
                        <span v-else class="text-5xs text-on-surface-variant/40">{{ __('No assignments') }}</span>
                    </div>
                </div>

                <!-- Lock button -->
                <div
                    v-if="canManage && isEncounterChecked(encounter.slug)"
                    @click.stop="emit('toggle-lock', encounter.slug)"
                    class="w-5 h-5 rounded flex items-center justify-center shrink-0 cursor-pointer transition-all"
                    :class="lockedBosses.has(encounter.slug)
                        ? 'bg-yellow-500/20 text-yellow-400'
                        : 'text-transparent group-hover:text-on-surface-variant/30 hover:!text-on-surface-variant/60'"
                    :title="lockedBosses.has(encounter.slug) ? __('Unlock roster') : __('Lock roster')"
                >
                    <span class="material-symbols-outlined text-xs">
                        {{ lockedBosses.has(encounter.slug) ? 'lock' : 'lock_open' }}
                    </span>
                </div>
            </button>
        </div>
    </div>
</template>
