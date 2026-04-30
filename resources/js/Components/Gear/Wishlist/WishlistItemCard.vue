<script setup>
import { computed } from 'vue';

const props = defineProps({
    item: { type: Object, required: true },
    difficultyLetter: { type: String, default: '' },
});

const isBis = computed(() => props.item.status === 'b');
const isDowngrade = computed(() => Number(props.item.value) < 0);
const isOutdated = computed(() => props.item.status === 'o');

const percentLabel = computed(() => {
    const n = Number(props.item.percent);
    if (Number.isNaN(n)) return '—';
    return `${n > 0 ? '+' : ''}${n.toFixed(2)}%`;
});

const valueLabel = computed(() => {
    const n = Number(props.item.value);
    if (Number.isNaN(n)) return '';
    return `${n > 0 ? '+' : ''}${n.toLocaleString()} dps`;
});

const displayName = computed(() => props.item.item_name || `Item #${props.item.item_id}`);

const wowheadHref = computed(() => {
    const params = [];
    if (props.item.item_level) params.push(`ilvl=${props.item.item_level}`);
    if (props.item.enchant_id) params.push(`ench=${props.item.enchant_id}`);
    const query = params.length ? `?${params.join('&')}` : '';
    return `https://www.wowhead.com/item=${props.item.item_id}${query}`;
});

const wowheadDataAttr = computed(() => {
    // Preferred way to feed the wowhead tooltip script — variant params land in
    // data-wowhead so the popover matches the URL even when href has cache-busting.
    const parts = [`item=${props.item.item_id}`];
    if (props.item.item_level) parts.push(`ilvl=${props.item.item_level}`);
    if (props.item.enchant_id) parts.push(`ench=${props.item.enchant_id}`);
    return parts.join('&');
});

const iconUrl = computed(() => {
    const raw = props.item.item_icon;
    if (!raw) return null;
    // Blizzard returns full URLs; older/manual entries may store just the icon slug.
    return raw.startsWith('http')
        ? raw
        : `https://wow.zamimg.com/images/wow/icons/medium/${raw}.jpg`;
});

const cardClass = computed(() => {
    if (isOutdated.value) return 'border-error/50 bg-error/5';
    if (isBis.value) return 'border-success-neon/70 bg-success-neon/5 shadow-[0_0_20px_-8px_rgba(74,222,128,0.55)]';
    if (isDowngrade.value) return 'border-white/5 bg-surface-container/40 opacity-60 grayscale';
    return 'border-white/10 bg-surface-container/60 hover:border-white/20';
});

const percentClass = computed(() => {
    if (isOutdated.value) return 'text-error';
    if (isBis.value) return 'text-success-neon';
    if (isDowngrade.value) return 'text-on-surface-variant';
    return 'text-yellow-300';
});
</script>

<template>
    <a
        :href="wowheadHref"
        :data-wowhead="wowheadDataAttr"
        target="_blank"
        rel="noopener"
        :class="['group relative flex items-center gap-3 px-3 py-2.5 rounded-lg border transition', cardClass]"
        :title="valueLabel"
    >
        <div class="shrink-0">
            <img
                v-if="iconUrl"
                :src="iconUrl"
                alt=""
                class="w-10 h-10 rounded ring-1 ring-white/10"
            />
            <div v-else class="w-10 h-10 rounded bg-surface-container ring-1 ring-white/10 flex items-center justify-center text-on-surface-variant text-[10px]">?</div>
        </div>

        <div class="min-w-0 flex-1">
            <div class="text-sm text-white font-medium truncate group-hover:text-cyan-200 transition">
                {{ displayName }}
            </div>
            <div class="text-[11px] text-on-surface-variant/80 truncate flex items-center gap-1.5">
                <span class="font-mono">{{ item.item_level || '—' }}</span>
                <span v-if="difficultyLetter" class="opacity-70">·</span>
                <span v-if="difficultyLetter" class="font-headline font-bold uppercase tracking-widest">{{ difficultyLetter }}</span>
            </div>
        </div>

        <div class="shrink-0 flex flex-col items-end gap-0.5">
            <div :class="['font-mono text-sm font-bold tabular-nums', percentClass]">
                {{ percentLabel }}
            </div>
            <div
                v-if="item.listed_in?.length"
                class="text-[9px] font-headline font-bold uppercase tracking-widest text-yellow-300/90 flex items-center gap-1"
                :title="item.listed_in.map(e => typeof e === 'string' ? e : (e.name + (e.tier ? ` (${e.tier})` : ''))).join(', ')"
            >
                <span class="material-symbols-outlined text-[12px]">star</span>
                <span>{{ item.listed_in.map(e => typeof e === 'string' ? e : (e.name + (e.tier ? ` ${e.tier}` : ''))).join(' · ') }}</span>
            </div>
        </div>
    </a>
</template>
