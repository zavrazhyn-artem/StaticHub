<template>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div v-for="role in roles" :key="role.key" class="relative h-8 rounded-xl overflow-hidden border border-white/5" :class="role.bgDim">
            <div
                class="absolute inset-y-0 left-0 rounded-xl transition-all duration-700"
                :class="role.barColor"
                :style="{ width: percent(role) + '%' }"
            ></div>
            <div class="relative z-10 flex items-center gap-2 h-full px-3">
                <img :src="`/images/roles/${role.icon}.svg`" class="w-4 h-4 opacity-90" :alt="__(role.label)">
                <span class="text-xs font-black text-white leading-none tabular-nums">{{ roleCounts[role.key] ?? 0 }}</span>
                <span class="text-4xs font-semibold text-white/40 tabular-nums">/ {{ role.max }}</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useTranslation } from '@/composables/useTranslation';
const { __ } = useTranslation();

const props = defineProps({
    roleCounts: { type: Object, default: () => ({}) },
})

const roles = [
    { key: 'tank', label: 'Tanks',   icon: 'tank',  max: 2,  barColor: 'bg-blue-500/60',   bgDim: 'bg-blue-500/10' },
    { key: 'heal', label: 'Healers', icon: 'heal',  max: 4,  barColor: 'bg-green-500/60',  bgDim: 'bg-green-500/10' },
    { key: 'mdps', label: 'Melee',   icon: 'melee', max: 6,  barColor: 'bg-red-500/60',    bgDim: 'bg-red-500/10' },
    { key: 'rdps', label: 'Ranged',  icon: 'range', max: 8,  barColor: 'bg-purple-500/60', bgDim: 'bg-purple-500/10' },
]

function percent(role) {
    return Math.min(100, ((props.roleCounts[role.key] ?? 0) / role.max) * 100)
}
</script>
