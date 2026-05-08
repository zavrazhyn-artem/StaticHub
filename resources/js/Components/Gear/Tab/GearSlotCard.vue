<script setup>
import { computed } from 'vue';
import { useTranslation } from '@/composables/useTranslation';

const { __ } = useTranslation();

const props = defineProps({
    slot: { type: String, required: true },
    item: { type: Object, default: null },
    enchantableSlots: { type: Array, default: () => [] },
    editable: { type: Boolean, default: false },
    mirror: { type: Boolean, default: false },
    audit: { type: Boolean, default: false },
    // Forwarded to wowhead so the tooltip resolves hybrid stats and tier
    // bonuses against the character's actual class/spec instead of a generic
    // "+N Agility or Intellect".
    classId: { type: [Number, null], default: null },
    specId: { type: [Number, null], default: null },
});

const emit = defineEmits(['edit', 'clear']);

// Roster's QUALITY_COLORS (kept identical so the two tabs read the same).
const QUALITY_COLORS = {
    POOR:      'text-gray-400',
    COMMON:    'text-white',
    UNCOMMON:  'text-green-400',
    RARE:      'text-blue-400',
    EPIC:      'text-purple-400',
    LEGENDARY: 'text-orange-400',
    ARTIFACT:  'text-yellow-200',
    HEIRLOOM:  'text-blue-200',
};

// Enchantable set is supplied by the page (config-driven, matches roster
// audit) — falls back to a sane built-in if the prop is empty.
const FALLBACK_ENCHANTABLE = ['head', 'shoulder', 'chest', 'legs', 'feet', 'finger_1', 'finger_2', 'main_hand', 'off_hand'];

// Mirrors SlotItemPicker / WishlistPanel — kept duplicated rather than
// extracted because each consumer slices the set slightly differently
// (here we don't need the slug-cleanup fallback). When an empty slot
// renders, we fall through to this so users see "Head" / "Голова"
// instead of a blank "empty" placeholder.
const SLOT_LABELS = {
    head: 'Head', neck: 'Neck', shoulder: 'Shoulder', back: 'Back', chest: 'Chest',
    wrist: 'Wrist', hands: 'Hands', waist: 'Waist', legs: 'Legs', feet: 'Feet',
    finger_1: 'Finger', finger_2: 'Finger', trinket_1: 'Trinket', trinket_2: 'Trinket',
    main_hand: 'Main Hand', off_hand: 'Off Hand', ranged: 'Ranged',
};
const slotLabel = computed(() => __(SLOT_LABELS[props.slot] || props.slot));

const iconUrl = computed(() => {
    if (!props.item?.item_icon) return null;
    const raw = props.item.item_icon;
    return raw.startsWith('http')
        ? raw
        : `https://wow.zamimg.com/images/wow/icons/medium/${raw}.jpg`;
});

const wowheadParams = computed(() => {
    if (!props.item) return [];
    const parts = [];
    if (props.item.item_level) parts.push(`ilvl=${props.item.item_level}`);
    if (props.item.enchant_id) parts.push(`ench=${props.item.enchant_id}`);
    if (props.item.gem_ids?.length) parts.push(`gems=${props.item.gem_ids.join(':')}`);
    if (props.item.bonus_ids?.length) parts.push(`bonus=${props.item.bonus_ids.join(':')}`);
    if (props.classId) parts.push(`cl=${props.classId}`);
    if (props.specId) parts.push(`spec=${props.specId}`);
    return parts;
});

const wowheadHref = computed(() => {
    if (!props.item) return null;
    const query = wowheadParams.value.length ? `?${wowheadParams.value.join('&')}` : '';
    return `https://www.wowhead.com/item=${props.item.item_id}${query}`;
});

const wowheadDataAttr = computed(() => {
    if (!props.item) return null;
    return [`item=${props.item.item_id}`, ...wowheadParams.value].join('&');
});

const displayName = computed(() => {
    if (!props.item) return null;
    return props.item.item_name || __('Item #:id', { id: props.item.item_id });
});

const qualityClass = computed(() => {
    const q = props.item?.item_quality ?? 'EPIC';
    return QUALITY_COLORS[q] ?? 'text-white';
});

const enchantableSet = computed(() => new Set(
    (props.enchantableSlots?.length ? props.enchantableSlots : FALLBACK_ENCHANTABLE)
));
const isEnchantable = computed(() => enchantableSet.value.has(props.slot));
const missingEnchant = computed(() => props.audit && !!props.item && isEnchantable.value && !props.item.enchant_id);
const missingSocket = computed(() => props.audit && !!props.item && !!props.item.has_empty_socket);
const hasMissingOptimization = computed(() => missingEnchant.value || missingSocket.value);
const showEnchantTag = computed(() => props.audit && !!props.item && isEnchantable.value);
</script>

<template>
    <div :class="[
        'group relative rounded-md border transition flex items-center gap-2 px-2 py-1',
        item ? 'border-white/10 bg-surface-container/60 hover:border-white/20'
              : 'border-dashed border-white/10 bg-surface-container/20',
        hasMissingOptimization ? '!border-red-500/60 shadow-[0_0_8px_-4px_rgba(239,68,68,0.6)]' : '',
        mirror ? 'flex-row-reverse text-right' : 'text-left'
    ]">
        <!-- Empty: editable rows turn the whole card into a click target,
             with the + symbol sitting where the item icon would normally be. -->
        <template v-if="!item">
            <button
                v-if="editable"
                type="button"
                @click="emit('edit')"
                :class="[
                    'flex items-center gap-2 flex-1 min-w-0 group/add',
                    mirror ? 'flex-row-reverse text-right' : 'text-left'
                ]"
            >
                <div class="w-8 h-8 rounded ring-1 ring-cyan-400/30 bg-cyan-500/5 flex items-center justify-center text-cyan-300/70 group-hover/add:ring-cyan-400/60 group-hover/add:bg-cyan-500/15 group-hover/add:text-cyan-200 transition shrink-0">
                    <span class="material-symbols-outlined text-lg leading-none">add</span>
                </div>
                <div class="flex-1 min-w-0 text-[11px] text-on-surface-variant/50 italic group-hover/add:text-on-surface-variant transition">
                    {{ slotLabel }}
                </div>
            </button>
            <template v-else>
                <div class="w-8 h-8 rounded bg-surface-container/40 ring-1 ring-white/5 flex items-center justify-center text-on-surface-variant/40 shrink-0">
                    <span class="material-symbols-outlined text-base">remove</span>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-[11px] text-on-surface-variant/50 italic">{{ slotLabel }}</div>
                </div>
            </template>
        </template>

        <!-- Filled -->
        <template v-else>
            <a
                :href="wowheadHref"
                :data-wowhead="wowheadDataAttr"
                target="_blank"
                rel="noopener"
                :class="[
                    'flex items-center gap-2.5 flex-1 min-w-0',
                    mirror ? 'flex-row-reverse' : ''
                ]"
            >
                <div class="relative w-8 h-8 shrink-0">
                    <img
                        v-if="iconUrl"
                        :src="iconUrl"
                        alt=""
                        class="w-8 h-8 rounded ring-1 ring-white/10"
                    />
                    <div v-else class="w-8 h-8 rounded bg-surface-container ring-1 ring-white/10 flex items-center justify-center text-on-surface-variant text-[10px]">?</div>
                    <!-- ilvl badge in quality color (corner placement matches roster) -->
                    <span
                        v-if="item.item_level"
                        :class="[
                            'absolute -top-1.5 -left-1.5 bg-black/85 px-1 py-px text-[10px] font-bold leading-none rounded font-mono tabular-nums shadow-sm border border-white/5',
                            qualityClass
                        ]"
                    >{{ item.item_level }}</span>
                    <!-- Missing-optimization badge (enchant and/or socket) -->
                    <span
                        v-if="hasMissingOptimization"
                        class="absolute -top-1.5 -right-1.5 bg-red-600 rounded-full w-3 h-3 flex items-center justify-center border border-[#0e0e10] z-10 shadow-sm"
                        :title="missingEnchant && missingSocket ? __('Missing enchant + empty socket') : (missingEnchant ? __('Missing enchant') : __('Empty socket'))"
                    >
                        <span class="text-[8px] text-white font-bold leading-none">!</span>
                    </span>
                    <!-- BiS-match badge (current list only — flag baked in by GearViewService) -->
                    <span
                        v-if="item.is_bis_match"
                        class="absolute -bottom-1.5 -right-1.5 bg-success-neon/90 px-1 py-px rounded text-[8px] font-headline font-black uppercase tracking-widest text-black leading-none border border-[#0e0e10] z-10 shadow-sm"
                        :title="__('Matches your BiS list')"
                    >BIS</span>
                </div>

                <div class="min-w-0 flex-1">
                    <div :class="['text-[11px] font-medium truncate leading-tight group-hover:text-cyan-200 transition', qualityClass]">
                        {{ displayName }}
                    </div>
                    <div v-if="showEnchantTag || missingSocket" :class="['text-[9px] flex items-center gap-1 leading-tight', mirror ? 'justify-end' : '']">
                        <span v-if="showEnchantTag && item.enchant_id" class="text-emerald-300/80">{{ __('ench') }}</span>
                        <span v-else-if="showEnchantTag" class="text-red-400/70">{{ __('no ench') }}</span>
                        <span v-if="missingSocket" class="text-red-400/70">{{ __('empty gem') }}</span>
                    </div>
                </div>
            </a>

            <div v-if="editable" :class="['flex items-center gap-0.5 shrink-0', mirror ? 'order-first' : '']">
                <button
                    type="button"
                    @click.stop="emit('edit')"
                    class="p-0.5 text-on-surface-variant hover:text-cyan-300 transition"
                    :title="__('Edit')"
                >
                    <span class="material-symbols-outlined text-sm">edit</span>
                </button>
                <button
                    type="button"
                    @click.stop="emit('clear')"
                    class="p-0.5 text-on-surface-variant hover:text-error transition"
                    :title="__('Clear')"
                >
                    <span class="material-symbols-outlined text-sm">close</span>
                </button>
            </div>
        </template>
    </div>
</template>
