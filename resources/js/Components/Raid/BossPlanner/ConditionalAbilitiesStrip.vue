<script setup>
import { computed } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
const { __ } = useTranslation();

const props = defineProps({
    abilities: { type: Array, default: () => [] },
});

const iconUrl = (a) => a.icon_filename ? `/images/cooldowns/${a.icon_filename}` : null;

const PRIORITY_RING = {
    high: 'ring-2 ring-red-500/70',
    medium: 'ring-1 ring-amber-400/60',
    low: 'ring-1 ring-white/20',
};

const formatTrigger = (t) => {
    if (!t) return '—';
    const translated = __('trigger_' + t);
    return translated === 'trigger_' + t ? t.replace(/_/g, ' ') : translated;
};
const formatResponse = (r) => {
    const translated = __('response_' + r);
    return translated === 'response_' + r ? r.replace(/_/g, ' ') : translated;
};
const localizedNotes = (a) => {
    const locale = (window.appLocale || 'en').toLowerCase();
    return a['notes_' + locale] || a.notes || '';
};

const hasAny = computed(() => props.abilities.length > 0);
</script>

<template>
    <div v-if="hasAny" class="shrink-0 border-b border-white/5 bg-[#0f0f12] px-4 py-2">
        <div class="flex items-center gap-2 mb-1.5">
            <span class="text-[8px] font-black uppercase tracking-widest text-on-surface-variant/50">{{ __('Conditional Mechanics') }}</span>
            <span class="text-[8px] text-on-surface-variant/30">· {{ __('not on a fixed schedule, triggered by raid state') }}</span>
        </div>
        <div class="flex items-center gap-2 overflow-x-auto">
            <div v-for="a in abilities" :key="a.id || a.spell_id"
                class="group flex items-center gap-2 px-2 py-1 rounded bg-white/5 hover:bg-white/10 transition-all whitespace-nowrap"
                :title="`${a.name}\n\n${__('Trigger:')} ${formatTrigger(a.trigger)}${a.recommended_response?.length ? '\n' + __('Response:') + ' ' + a.recommended_response.map(formatResponse).join(', ') : ''}${localizedNotes(a) ? '\n\n' + localizedNotes(a) : ''}`">
                <div class="shrink-0 w-5 h-5 rounded overflow-hidden relative"
                    :class="PRIORITY_RING[a.priority] || PRIORITY_RING.medium">
                    <img v-if="iconUrl(a)" :src="iconUrl(a)" :alt="a.name" class="w-full h-full object-cover" />
                    <div v-else class="w-full h-full" :style="{ background: a.color }"></div>
                </div>
                <div class="flex flex-col gap-0">
                    <span class="text-[10px] font-semibold text-white leading-none">{{ a.name }}</span>
                    <span class="text-[8px] text-on-surface-variant/60 leading-tight">{{ formatTrigger(a.trigger) }}</span>
                </div>
                <div v-if="a.recommended_response?.length" class="flex items-center gap-0.5">
                    <span v-for="r in a.recommended_response" :key="r"
                        class="text-[7px] font-black uppercase tracking-wider px-1 py-0.5 rounded bg-primary/15 text-primary/80">
                        {{ formatResponse(r) }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
