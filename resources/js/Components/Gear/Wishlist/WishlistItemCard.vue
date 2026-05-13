<script setup>
import { computed } from 'vue';
import { useTranslation } from '@/composables/useTranslation';

const { __ } = useTranslation();

const props = defineProps({
    item: { type: Object, required: true },
    difficultyLetter: { type: String, default: '' },
});

const emit = defineEmits(['open-claimants']);

const claimantCount = computed(() => Number(props.item.claimant_count ?? 0));
const bisCount = computed(() => Number(props.item.bis_count ?? 0));

// Green highlight = the item is on this character/spec's BiS gear list.
// Distinct from `status === 'b'` (which is raidbots' "BiS-tier upgrade"
// flag from the simulator) — the user's BiS-list membership is what
// matters for loot-council priority calls.
const isBis = computed(() =>
    Array.isArray(props.item.listed_in)
    && props.item.listed_in.some(e => typeof e === 'object' && e?.type === 'bis'));
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
    return `${n > 0 ? '+' : ''}${n.toLocaleString()} ${__('dps')}`;
});

const displayName = computed(() => props.item.item_name || __('Item #:id', { id: props.item.item_id }));

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
    <!-- Anchor (not button) so Wowhead's tooltips.js latches onto it via the
         wowhead.com href — it's flaky on non-<a> elements. Native click goes
         to wowhead.com (which we cancel) so the only side effect is opening
         the claimants modal. No `title` attribute on purpose — the native
         browser tooltip would race and win over the Wowhead popup. -->
    <a
        :href="wowheadHref"
        :data-wowhead="wowheadDataAttr"
        @click.prevent="emit('open-claimants', item)"
        :class="['group relative flex items-center gap-3 px-3 py-2.5 rounded-lg border transition w-full text-left cursor-pointer hover:brightness-110 no-underline', cardClass]"
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
                <template v-if="item.boss_name">
                    <span class="opacity-70">·</span>
                    <span class="text-orange-300/80 font-medium truncate">{{ item.boss_name }}</span>
                </template>
            </div>
        </div>

        <div class="shrink-0 flex flex-col items-end gap-0.5">
            <div :class="['font-mono text-sm font-bold tabular-nums', percentClass]">
                {{ percentLabel }}
            </div>
            <!-- Loot-council counter: total claimants + how many of them
                 have this as their BiS. Click on the card opens the modal
                 with the full breakdown. -->
            <div
                v-if="claimantCount > 0"
                class="flex items-center gap-1.5 text-[10px] font-headline font-bold uppercase tracking-widest"
            >
                <span class="flex items-center gap-0.5 text-on-surface-variant">
                    <span class="material-symbols-outlined text-[12px]">groups</span>
                    {{ claimantCount }}
                </span>
                <span v-if="bisCount > 0" class="flex items-center gap-0.5 text-yellow-300">
                    <span class="material-symbols-outlined text-[12px]">star</span>
                    {{ bisCount }}
                </span>
            </div>
            <div
                v-if="item.listed_in?.length"
                class="text-[9px] font-headline font-bold uppercase tracking-widest text-yellow-300/90 flex items-center gap-1"
                :title="item.listed_in.map(e => typeof e === 'string' ? e : (e.name + (e.tier ? ` (${e.tier})` : ''))).join(', ')"
            >
                <span class="material-symbols-outlined text-[12px]">checklist</span>
                <span>{{ item.listed_in.map(e => typeof e === 'string' ? e : (e.name + (e.tier ? ` ${e.tier}` : ''))).join(' · ') }}</span>
            </div>
            <!-- Which Droptimizer configs contributed to the weighted
                 percent above. Two-letter abbrev keeps the corner tight;
                 full names are in the title attribute. -->
            <div
                v-if="item.matched_configs?.length"
                class="text-[9px] font-headline font-bold uppercase tracking-widest text-cyan-300/70 flex items-center gap-1"
                :title="__('Weighted across: :names', { names: item.matched_configs.join(', ') })"
            >
                <span class="material-symbols-outlined text-[12px]">tune</span>
                <span>{{ item.matched_configs.map(n => n.slice(0, 2)).join('/') }}</span>
            </div>
        </div>
    </a>
</template>
