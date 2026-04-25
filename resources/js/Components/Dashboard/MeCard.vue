<template>
    <div v-if="me" class="h-full flex flex-col">
        <!-- Header: avatar + class-colored name -->
        <div class="flex items-center gap-3 mb-3">
            <img
                v-if="me.avatarUrl"
                :src="me.avatarUrl"
                :alt="me.characterName"
                class="w-10 h-10 rounded-lg object-cover border-2"
                :style="{ borderColor: classColor + 'aa' }"
            >
            <div
                v-else
                class="w-10 h-10 rounded-lg grid place-items-center font-extrabold text-base"
                :style="{
                    background: `linear-gradient(135deg, ${classColor}66, ${classColor}22)`,
                    border: `2px solid ${classColor}aa`,
                    color: classColor,
                }"
            >{{ initial }}</div>
            <div class="min-w-0">
                <div class="text-[11px] uppercase tracking-[0.16em] font-bold" :style="{ color: classColor }">
                    {{ __('You') }} · {{ me.characterName }}
                </div>
                <div class="text-sm font-extrabold mt-0.5">{{ specLabel }}</div>
            </div>
        </div>

        <!-- 4 stat tiles -->
        <div class="grid grid-cols-2 gap-2">
            <stat-tile :label="__('Item Level')" :value="me.itemLevel || null" :color="classColor"/>
            <stat-tile :label="__('M+ Score')"   :value="me.mplusScore || null" color="#4fd3f7"/>
            <stat-tile :label="__('Vault')"      :value="me.vaultText" color="#39FF14"/>
            <stat-tile :label="__('Best Key')"   :value="me.bestKey ? `+${me.bestKey}` : null" color="#fa7902"/>
        </div>

        <!-- Readiness banner — placeholder until gear-audit hook -->
        <div
            v-if="me.readinessPct !== null"
            class="mt-3 px-3 py-2 rounded-lg text-[10px] font-bold flex items-center gap-1.5"
            :class="readinessClass"
        >
            <span>●</span>
            {{ __('Readiness') }} {{ me.readinessPct }}%
        </div>
    </div>

    <div v-else class="text-on-surface-variant text-sm">{{ __('No main character set') }}</div>
</template>

<script setup>
import { computed } from 'vue'
import { useTranslation } from '@/composables/useTranslation'
import { useWowClasses } from '@/composables/useWowClasses'
import StatTile from './StatTile.vue'

const { __ } = useTranslation()
const { getClassTextColor } = useWowClasses()

const props = defineProps({
    me: { type: Object, default: null },
})

const classColor = computed(() => getClassTextColor(props.me?.playableClass))
const initial    = computed(() => (props.me?.characterName?.[0] ?? '?').toUpperCase())

const specLabel = computed(() => {
    const spec = props.me?.specName
    const cls  = props.me?.playableClass
    if (spec && cls) return `${spec} ${cls}`
    return cls ?? '—'
})

const readinessClass = computed(() => {
    const p = props.me?.readinessPct ?? 0
    if (p >= 90) return 'bg-success-neon/10 border border-success-neon/20 text-success-neon'
    if (p >= 70) return 'bg-tertiary-dim/10 border border-tertiary-dim/20 text-tertiary-dim'
    return 'bg-error/10 border border-error/20 text-error'
})
</script>
