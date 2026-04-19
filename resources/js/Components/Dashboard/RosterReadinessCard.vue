<template>
    <div class="bg-surface-container-high rounded-2xl border border-white/5 p-6">
        <h2 class="text-on-surface-variant font-headline text-xs font-bold uppercase tracking-widest mb-6">{{ __('Roster Readiness') }}</h2>
        <div class="space-y-4">
            <div v-for="role in roles" :key="role.key" class="space-y-1">
                <div class="flex justify-between text-xs font-bold uppercase tracking-wider">
                    <span class="text-white">{{ __(role.label) }}</span>
                    <span class="text-on-surface-variant">{{ roleCounts[role.key] ?? 0 }} / {{ role.max }}</span>
                </div>
                <div class="h-2 bg-black/40 rounded-full overflow-hidden">
                    <div
                        class="h-full shadow-[0_0_10px_rgba(0,0,0,0.5)] transition-all duration-1000"
                        :class="role.color"
                        :style="{ width: percent(role) + '%' }"
                    ></div>
                </div>
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
    { key: 'tank', label: 'Tanks',   color: 'bg-blue-500',    max: 2 },
    { key: 'heal', label: 'Healers', color: 'bg-success-neon', max: 4 },
    { key: 'mdps', label: 'Melee',   color: 'bg-error-dim',   max: 6 },
    { key: 'rdps', label: 'Ranged',  color: 'bg-purple-500',  max: 8 },
]

function percent(role) {
    return Math.min(100, ((props.roleCounts[role.key] ?? 0) / role.max) * 100)
}
</script>
