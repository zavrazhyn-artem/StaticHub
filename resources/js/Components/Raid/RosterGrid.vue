<script setup>
import { computed } from 'vue';
import RosterCard from './RosterCard.vue';
import { useTranslation } from '@/composables/useTranslation';
const { __ } = useTranslation();

const props = defineProps({
    mainRoster: { type: Object, required: true },
    absentRoster: { type: Array, default: () => [] },
});

const emit = defineEmits(['open-comment']);

const roles = {
    tank: { label: __('Tanks'), icon: 'tank.svg', color: 'text-blue-400', bg: 'bg-blue-400/5', capacity: 6 },
    heal: { label: __('Healers'), icon: 'heal.svg', color: 'text-green-500', bg: 'bg-green-500/5', capacity: 12 },
    mdps: { label: __('Melee DPS'), icon: 'melee.svg', color: 'text-red-500', bg: 'bg-red-500/5', capacity: 18 },
    rdps: { label: __('Ranged DPS'), icon: 'range.svg', color: 'text-red-500', bg: 'bg-red-500/5', capacity: 18 },
};

// МАГІЯ СОРТУВАННЯ ТА СІТКИ
// МАГІЯ СОРТУВАННЯ ТА СІТКИ
const layoutData = computed(() => {
    const data = {};

    // Жорсткий порядок статусів (чим менше число, тим вище в списку)
    const statusWeights = {
        present: 1,
        late: 2,
        pending: 3,
        tentative: 4,
        absent: 5
    };

    const getWeight = (status) => statusWeights[status] || 99;

    for (const roleKey of ['tank', 'heal', 'mdps', 'rdps']) {
        // 1. Сортуємо основний ростер: Present -> Late -> Pending
        const mainChars = [...(props.mainRoster[roleKey] || [])].sort((a, b) => {
            return getWeight(a.pivot?.status) - getWeight(b.pivot?.status);
        });
        const presentCount = mainChars.length;

        // 2. Сортуємо відсутніх: Tentative -> Absent
        const absentChars = props.absentRoster
            .filter(c => c.assigned_role === roleKey)
            .sort((a, b) => {
                return getWeight(a.pivot?.status) - getWeight(b.pivot?.status);
            });
        const absentCount = absentChars.length;

        const capacity = roles[roleKey].capacity;
        const span = (presentCount > 0 && presentCount <= capacity / 2) ? 2 : 1;

        // Порожні слоти автоматично опиняться МІЖ mainChars та absentChars
        const emptyCount = Math.max(0, capacity - (presentCount * span) - absentCount);

        data[roleKey] = {
            cardClass: span === 2 ? 'col-span-2' : 'col-span-1',
            emptyCount,
            mainChars,
            absentChars
        };
    }

    return data;
});
</script>

<template>
    <div class="flex flex-col gap-4">

        <div
            v-for="roleKey in ['tank', 'heal', 'mdps', 'rdps']"
            :key="roleKey"
            class="bg-surface-container/60 border border-white/5 rounded-xl overflow-hidden flex flex-col backdrop-blur-sm w-full"
        >
            <div class="px-4 py-2 border-b border-white/5 flex items-center justify-between" :class="roles[roleKey].bg">
                <div class="flex items-center gap-2">
                    <img :src="'/images/roles/' + roles[roleKey].icon" class="w-4 h-4 opacity-80" :alt="roles[roleKey].label">
                    <span class="font-headline text-xs font-black uppercase tracking-widest" :class="roles[roleKey].color">{{ roles[roleKey].label }}</span>
                </div>

                <div class="flex items-center gap-2">
                    <span v-if="layoutData[roleKey].absentChars.length" class="text-4xs font-black text-error-dim uppercase tracking-wider">
                        {{ layoutData[roleKey].absentChars.length }} {{ __('Absent') }}
                    </span>
                    <span class="bg-white/10 px-2 py-0.5 rounded text-3xs font-black text-white">
                        {{ layoutData[roleKey].mainChars.length }} / {{ roles[roleKey].capacity }}
                    </span>
                </div>
            </div>

            <div class="p-2 grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-2">

                <div
                    v-for="character in layoutData[roleKey].mainChars"
                    :key="'main-' + character.id"
                    :class="layoutData[roleKey].cardClass"
                >
                    <RosterCard :character="character" class="h-full" @open-comment="emit('open-comment', $event)" />
                </div>

                <div
                    v-for="i in layoutData[roleKey].emptyCount"
                    :key="'empty-' + i"
                    class="flex items-center justify-center p-2 opacity-20 border border-dashed border-white/10 rounded min-h-[34px] col-span-1"
                >
                    <span class="text-5xs uppercase font-black tracking-wider text-on-surface-variant">{{ __('Empty') }}</span>
                </div>

                <div
                    v-for="character in layoutData[roleKey].absentChars"
                    :key="'absent-' + character.id"
                    class="col-span-1 transition-all duration-300"
                    :class="character.pivot?.status === 'tentative'
                        ? 'opacity-70 saturate-50 hover:opacity-100 hover:saturate-100'
                        : 'opacity-40 grayscale hover:opacity-100 hover:grayscale-0'"
                >
                    <RosterCard
                        :character="character"
                        class="h-full border"
                        :class="character.pivot?.status === 'tentative'
                            ? 'border-yellow-500/30 bg-yellow-500/5'
                            : 'border-error-dim/30 bg-error-dim/5'"
                        @open-comment="emit('open-comment', $event)"
                    />
                </div>

            </div>
        </div>

    </div>
</template>
