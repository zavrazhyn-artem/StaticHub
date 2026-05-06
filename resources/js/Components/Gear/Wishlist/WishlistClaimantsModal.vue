<script setup>
import { computed } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
import GlassModal from '@/Components/UI/GlassModal.vue';

const { __ } = useTranslation();

const props = defineProps({
    show: { type: Boolean, default: false },
    item: { type: Object, default: null },
});

const emit = defineEmits(['close']);

// WoW class colors — same palette used by SelectUserWithMain so a Druid name
// here reads the same orange as in the character picker.
const CLASS_COLORS = {
    'Death Knight': '#C41F3B', 'Demon Hunter': '#A330C9',
    'Druid':        '#FF7C0A', 'Evoker':       '#33937F',
    'Hunter':       '#ABD473', 'Mage':         '#3FC7EB',
    'Monk':         '#00FF98', 'Paladin':      '#F48CBA',
    'Priest':       '#FFFFFF', 'Rogue':        '#FFF468',
    'Shaman':       '#0070DD', 'Warlock':      '#8788EE',
    'Warrior':      '#C69B6D',
};
const classColor = (cls) => CLASS_COLORS[cls] ?? '#9ca3af';

// Sort: BiS first, then main spec ahead of off-spec, then by upgrade value
// desc — most-deserving claimants land at the top so a loot caller can scan.
const sortedClaimants = computed(() => {
    const list = [...(props.item?.claimants ?? [])];
    return list.sort((a, b) => {
        if (a.is_bis !== b.is_bis) return a.is_bis ? -1 : 1;
        if (a.is_main_spec !== b.is_main_spec) return a.is_main_spec ? -1 : 1;
        return Number(b.value || 0) - Number(a.value || 0);
    });
});

const wowheadHref = computed(() => {
    if (!props.item) return '#';
    const params = [];
    if (props.item.item_level) params.push(`ilvl=${props.item.item_level}`);
    if (props.item.enchant_id) params.push(`ench=${props.item.enchant_id}`);
    return `https://www.wowhead.com/item=${props.item.item_id}${params.length ? '?' + params.join('&') : ''}`;
});

const wowheadDataAttr = computed(() => {
    if (!props.item) return null;
    const parts = [`item=${props.item.item_id}`];
    if (props.item.item_level) parts.push(`ilvl=${props.item.item_level}`);
    if (props.item.enchant_id) parts.push(`ench=${props.item.enchant_id}`);
    return parts.join('&');
});

const iconUrl = computed(() => {
    const raw = props.item?.item_icon;
    if (!raw) return null;
    return raw.startsWith('http') ? raw : `https://wow.zamimg.com/images/wow/icons/medium/${raw}.jpg`;
});

const fmtValue = (v) => {
    const n = Number(v ?? 0);
    if (Number.isNaN(n)) return '';
    return `${n > 0 ? '+' : ''}${n.toLocaleString()}`;
};

const fmtPercent = (v) => {
    const n = Number(v ?? 0);
    if (Number.isNaN(n)) return '';
    return `${n > 0 ? '+' : ''}${n.toFixed(2)}%`;
};
</script>

<template>
    <GlassModal :show="show" @close="emit('close')" max-width="max-w-2xl">
        <header class="flex items-center justify-between px-6 py-4 border-b border-white/10">
            <div class="flex items-center gap-3 min-w-0">
                <img v-if="iconUrl" :src="iconUrl" :alt="item?.item_name" class="w-10 h-10 rounded ring-1 ring-white/10 shrink-0" />
                <div class="min-w-0">
                    <a
                        :href="wowheadHref"
                        :data-wowhead="wowheadDataAttr"
                        target="_blank"
                        rel="noopener"
                        class="font-headline text-sm font-black text-white uppercase tracking-widest truncate hover:text-cyan-300 transition flex items-center gap-2"
                    >
                        {{ item?.item_name ?? __('Item') }}
                        <span class="material-symbols-outlined text-base text-on-surface-variant/60">open_in_new</span>
                    </a>
                    <div class="text-[10px] text-on-surface-variant uppercase tracking-widest mt-0.5 flex items-center gap-1.5">
                        <span v-if="item?.item_level" class="font-mono">{{ item.item_level }}</span>
                        <template v-if="item?.boss_name">
                            <span>·</span>
                            <span class="text-orange-300/80">{{ item.boss_name }}</span>
                        </template>
                    </div>
                </div>
            </div>
            <button type="button" @click="emit('close')" class="text-on-surface-variant hover:text-white transition">
                <span class="material-symbols-outlined">close</span>
            </button>
        </header>

        <div class="max-h-[60vh] overflow-y-auto custom-scrollbar p-4 space-y-1.5">
            <div v-if="!sortedClaimants.length" class="text-center py-12 text-on-surface-variant text-sm italic">
                {{ __('No one in this static has this item in their wishlist.') }}
            </div>

            <div
                v-for="(c, idx) in sortedClaimants"
                :key="`${c.character_id}-${c.spec_id}`"
                class="flex items-center gap-3 px-3 py-2 rounded-lg border transition"
                :class="c.is_bis
                    ? 'border-success-neon/40 bg-success-neon/5'
                    : 'border-white/10 bg-surface-container/40 hover:bg-white/5'"
            >
                <span class="font-mono text-[11px] text-on-surface-variant/40 w-5 text-right tabular-nums shrink-0">{{ idx + 1 }}</span>

                <img
                    v-if="c.avatar_url"
                    :src="c.avatar_url"
                    :alt="c.character_name"
                    class="w-8 h-8 rounded ring-1 ring-white/10 shrink-0"
                />
                <div v-else class="w-8 h-8 rounded bg-white/5 ring-1 ring-white/10 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-base text-on-surface-variant/40">person</span>
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5 min-w-0">
                        <span
                            class="font-headline text-sm font-bold truncate"
                            :style="{ color: classColor(c.playable_class) }"
                        >{{ c.character_name }}</span>
                        <span
                            class="px-1.5 py-px rounded text-[9px] font-bold uppercase tracking-widest border shrink-0"
                            :class="c.role === 'main'
                                ? 'border-yellow-300/60 bg-yellow-500/10 text-yellow-200'
                                : 'border-white/10 bg-white/5 text-on-surface-variant/70'"
                        >{{ c.role === 'main' ? __('Main') : __('Alt') }}</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-[10px] text-on-surface-variant uppercase tracking-widest mt-0.5">
                        <span>{{ c.spec_name }}</span>
                        <span
                            v-if="!c.is_main_spec"
                            class="px-1.5 py-px rounded text-[9px] font-bold normal-case tracking-normal border border-purple-400/40 bg-purple-500/10 text-purple-200"
                            :title="__('Off-spec wishlist')"
                        >off-spec</span>
                    </div>
                </div>

                <div class="shrink-0 flex flex-col items-end gap-0.5">
                    <div :class="['font-mono text-sm font-bold tabular-nums', c.is_bis ? 'text-success-neon' : 'text-yellow-300/80']">
                        {{ fmtPercent(c.percent) }}
                    </div>
                    <div v-if="c.is_bis" class="flex items-center gap-0.5 text-[9px] font-headline font-black uppercase tracking-widest text-success-neon">
                        <span class="material-symbols-outlined text-[11px]">star</span>BIS
                    </div>
                    <div v-else class="text-[9px] font-mono text-on-surface-variant/60 tabular-nums">
                        {{ fmtValue(c.value) }}
                    </div>
                </div>
            </div>
        </div>
    </GlassModal>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: rgba(255,255,255,.05); }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,.15); border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,.25); }
</style>
