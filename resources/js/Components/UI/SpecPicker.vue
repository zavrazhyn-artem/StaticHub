<script setup>
import { computed } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
const { __ } = useTranslation();

const props = defineProps({
    allSpecs:        { type: Array,  required: true },
    characterClass:  { type: String, default: '' },
    selectedSpecIds: { type: Array,  default: () => [] },
    mainSpecId:      { type: [Number, null], default: null },
});

const emit = defineEmits(['update:selectedSpecIds', 'update:mainSpecId']);

const classSpecs = computed(() =>
    props.allSpecs.filter(s => s.class_name === props.characterClass)
);

const roleLabel = { tank: __('Tank'), heal: __('Heal'), mdps: __('Melee'), rdps: __('Ranged') };

const roleBadgeClass = {
    tank: 'bg-blue-500/20 text-blue-300 border-blue-500/30',
    heal: 'bg-green-500/20 text-green-300 border-green-500/30',
    mdps: 'bg-red-500/20 text-red-300 border-red-500/30',
    rdps: 'bg-purple-500/20 text-purple-300 border-purple-500/30',
};

function isSelected(specId) {
    return props.selectedSpecIds.includes(specId);
}

function toggleSpec(specId) {
    const current = [...props.selectedSpecIds];
    const idx = current.indexOf(specId);

    if (idx === -1) {
        emit('update:selectedSpecIds', [...current, specId]);
        if (!props.mainSpecId) {
            emit('update:mainSpecId', specId);
        }
    } else {
        // Cannot deselect the main spec
        if (specId === props.mainSpecId) return;
        current.splice(idx, 1);
        emit('update:selectedSpecIds', current);
    }
}

function setMain(specId) {
    if (!isSelected(specId)) {
        emit('update:selectedSpecIds', [...props.selectedSpecIds, specId]);
    }
    emit('update:mainSpecId', specId);
}
</script>

<template>
    <div class="space-y-2">
        <p v-if="classSpecs.length === 0" class="text-xs text-on-surface-variant text-center py-4">
            {{ __('No specializations found for this class.') }}
        </p>

        <div class="grid grid-cols-3 gap-2">
            <button
                v-for="spec in classSpecs"
                :key="spec.id"
                type="button"
                @click="toggleSpec(spec.id)"
                class="relative flex flex-col items-center gap-1.5 p-2 rounded-xl border transition-all text-center"
                :class="isSelected(spec.id)
                    ? 'border-primary/60 bg-primary/10'
                    : 'border-white/5 bg-white/5 hover:border-white/20 hover:bg-white/10'"
            >
                <!-- Star in top-left (only for selected specs) -->
                <button
                    v-if="isSelected(spec.id)"
                    type="button"
                    @click.stop="setMain(spec.id)"
                    class="absolute top-1 left-1 transition-colors leading-none"
                    :class="mainSpecId === spec.id ? 'text-yellow-400' : 'text-white/25 hover:text-yellow-300'"
                    :title="mainSpecId === spec.id ? __('Main spec') : __('Set as main')"
                >
                    <span class="material-symbols-outlined text-xs">star</span>
                </button>

                <!-- Spec icon -->
                <img
                    :src="spec.icon_url"
                    :alt="spec.name"
                    class="w-10 h-10 rounded-lg object-cover transition-opacity"
                    :class="isSelected(spec.id) ? 'opacity-100' : 'opacity-30'"
                >

                <!-- Spec name -->
                <span
                    class="text-3xs font-semibold leading-tight"
                    :class="isSelected(spec.id) ? 'text-white' : 'text-on-surface-variant/60'"
                >{{ spec.name }}</span>

                <!-- Role badge -->
                <span
                    class="text-5xs font-black uppercase tracking-wider px-1.5 py-0.5 rounded-full border"
                    :class="[
                        roleBadgeClass[spec.role] ?? 'bg-white/10 text-white/60 border-white/10',
                        !isSelected(spec.id) && 'opacity-40',
                    ]"
                >{{ roleLabel[spec.role] ?? spec.role }}</span>
            </button>
        </div>
    </div>
</template>
