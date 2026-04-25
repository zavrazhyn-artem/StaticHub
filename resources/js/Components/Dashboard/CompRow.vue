<template>
    <div>
        <div class="flex justify-between items-baseline mb-1">
            <span class="text-[11px] text-on-surface-variant font-semibold">{{ label }}</span>
            <span class="text-[11px] font-mono font-bold" :style="{ color }">{{ have }} / {{ need }}</span>
        </div>
        <div class="h-1.5 bg-white/[0.06] rounded-full overflow-hidden">
            <div
                class="h-full rounded-full"
                :style="{
                    width: pct + '%',
                    background: `linear-gradient(90deg, ${color}88, ${color})`,
                    boxShadow: `0 0 6px ${color}aa`,
                }"
            ></div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    label: { type: String, required: true },
    have:  { type: Number, required: true },
    need:  { type: Number, required: true },
    color: { type: String, default: '#4fd3f7' },
})

const pct = computed(() => Math.min(100, Math.round((props.have / Math.max(1, props.need)) * 100)))
</script>
