<script setup>
import { computed } from 'vue';
import { useTranslation } from '@/composables/useTranslation';

const { __ } = useTranslation();

const props = defineProps({
    readiness: { type: Object, required: true },
});

const emit = defineEmits(['focus-slot']);

const r = computed(() => props.readiness);
const ok = computed(() => (r.value.problems ?? 0) === 0);

const chips = computed(() => {
    const out = [];
    for (const m of r.value.missing_enchants ?? []) {
        out.push({ key: `me-${m.slot}`, slot: m.slot, icon: 'block', label: __('Missing enchant'), detail: m.name });
    }
    for (const m of r.value.low_quality_enchants ?? []) {
        out.push({ key: `lq-${m.slot}`, slot: m.slot, icon: 'trending_down', label: __('Weak enchant'), detail: m.name });
    }
    if ((r.value.empty_sockets ?? 0) > 0) {
        out.push({ key: 'sockets', slot: null, icon: 'data_object', label: __('Empty socket'), detail: String(r.value.empty_sockets) });
    }
    if (r.value.weakest) {
        const w = r.value.weakest;
        out.push({ key: 'weak', slot: w.slot, icon: 'south', label: __('Weakest slot'), detail: `${w.name} ${w.ilvl} (${w.delta})` });
    }
    return out;
});

function onChip(chip) {
    if (chip.slot) emit('focus-slot', chip.slot);
}
</script>

<template>
    <div
        class="mb-4 rounded-xl border bg-surface-container-high px-4 py-3"
        :class="ok ? 'border-success-neon/30' : 'border-error/30'"
    >
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
            <div class="flex items-center gap-2 font-headline">
                <span
                    class="material-symbols-outlined text-xl"
                    :class="ok ? 'text-success-neon' : 'text-error'"
                >{{ ok ? 'verified' : 'warning' }}</span>
                <span :class="ok ? 'text-success-neon' : 'text-error'">
                    {{ ok ? __('Ready for raid') : `${r.problems} ${__('issues')}` }}
                </span>
            </div>
            <span v-if="r.ilvl" class="text-sm text-on-surface-variant">
                ilvl <span class="font-semibold text-on-surface">{{ r.ilvl }}</span>
            </span>
            <span class="text-sm text-on-surface-variant">
                {{ __('Tier') }} <span class="font-semibold text-on-surface">{{ r.tier_have }}/{{ r.tier_total }}</span>
            </span>
        </div>

        <div v-if="chips.length" class="mt-2 flex flex-wrap gap-2">
            <button
                v-for="chip in chips"
                :key="chip.key"
                type="button"
                :disabled="!chip.slot"
                class="inline-flex items-center gap-1.5 rounded-lg bg-error/10 px-2.5 py-1 text-xs text-error transition hover:bg-error/20 disabled:cursor-default disabled:opacity-80"
                @click="onChip(chip)"
            >
                <span class="material-symbols-outlined text-sm">{{ chip.icon }}</span>
                <span class="font-medium">{{ chip.label }}:</span>
                <span class="text-on-surface-variant">{{ chip.detail }}</span>
            </button>
        </div>
    </div>
</template>
