<script setup>
import { inject, computed } from 'vue';

const rowHeights = inject('rowHeights');
const ilvlTiers  = inject('ilvlTiers', []);

const props = defineProps({
    char: { type: Object, required: true },
    tierColors: { type: Object, required: true },
    tierCount: { type: Function, required: true },
    isAlt: { type: Boolean, default: false },
});

const rh = computed(() => props.isAlt ? rowHeights.alt : rowHeights.main);

const TIER_SLOTS = ['H', 'S', 'C', 'G', 'L'];
const TIER_SLOT_LABELS = { H: 'Head', S: 'Shoulders', C: 'Chest', G: 'Gloves', L: 'Legs' };

// Track colours — match gear-upgrade scale exactly
const TIER_TRACK_COLORS = {
    Myth:       '#FB923C', // orange
    Hero:       '#C084FC', // purple
    Champion:   '#60A5FA', // blue
    Veteran:    '#4ADE80', // green
    Adventurer: '#2dd4bf',
    Explorer:   '#9ca3af',
};
const TIER_TRACK_ABBREV = {
    Myth: 'M', Hero: 'H', Champion: 'C', Veteran: 'V', Adventurer: 'A', Explorer: 'E',
};

const pipColor  = (slot) => TIER_TRACK_COLORS[props.char?.tier_pieces?.[slot]] ?? null;
const pipAbbrev = (slot) => TIER_TRACK_ABBREV[props.char?.tier_pieces?.[slot]] ?? null;
const pipTitle  = (slot) => `${TIER_SLOT_LABELS[slot]}: ${props.char?.tier_pieces?.[slot] || 'None'}`;

// iLvL colour — driven by season config tiers (injected from UnifiedRoster)
const ilvlColor = computed(() => {
    const v = props.char?.equipped_ilvl ?? 0;
    const tiers = ilvlTiers.value ?? ilvlTiers; // reactive ref or plain array
    for (const tier of tiers) {
        if (v >= tier.min) return tier.color;
    }
    return '#9ca3af';
});

const runs = computed(() => props.char?.weekly_runs_count ?? 0);
const runsColor = computed(() => {
    if (runs.value >= 8) return '#39FF14';
    if (runs.value >= 4) return '#fcf266';
    return '#ff6e84';
});
const runsBarPct = computed(() => Math.min((runs.value / 8) * 100, 100));

// Rating colour thresholds: <1000=gray, 1000-2000=green, 2000-3000=blue, 3000-3500=purple, 3500+=orange
const ratingColor = computed(() => {
    const r = props.char?.mythic_rating ?? 0;
    if (r >= 3500) return '#FB923C';
    if (r >= 3000) return '#C084FC';
    if (r >= 2000) return '#60A5FA';
    if (r >= 1000) return '#4ADE80';
    return '#9ca3af';
});
</script>

<template>
    <!-- iLvL -->
    <td :class="[rh, isAlt ? 'px-1 py-0.5' : 'p-2.5', 'text-center font-mono font-bold border-l border-white/[0.06]']"
        :style="{ color: ilvlColor }">
        {{ char?.equipped_ilvl != null ? Number(char.equipped_ilvl).toFixed(1) : 'N/A' }}
    </td>

    <!-- Tier Pips — 5 coloured segments with slot tooltip -->
    <td :class="[rh, isAlt ? 'px-2 py-0.5' : 'px-3 py-2.5', 'border-l border-white/[0.06] text-center']">
        <div class="inline-flex items-center gap-[3px]">
            <div v-for="slot in TIER_SLOTS" :key="slot"
                 :class="isAlt ? 'w-[6px] h-[10px]' : 'w-[10px] h-[14px]'"
                 class="rounded-[2px] flex items-center justify-center cursor-default"
                 :style="pipColor(slot)
                     ? { background: pipColor(slot) + 'bb', border: `1px solid ${pipColor(slot)}`, boxShadow: `0 0 3px ${pipColor(slot)}55` }
                     : { background: 'transparent', border: '1px dashed rgba(255,255,255,0.12)' }"
                 :title="pipTitle(slot)">
                <span v-if="!isAlt && pipAbbrev(slot)"
                      class="font-mono font-black leading-none select-none"
                      style="font-size: 7px; color: #0e0e10;">
                    {{ pipAbbrev(slot) }}
                </span>
            </div>
        </div>
    </td>

    <!-- M+ Runs — number + mini progress bar -->
    <td :class="[rh, isAlt ? 'px-2 py-0.5' : 'px-3 py-2.5', 'border-l border-white/[0.06]']">
        <div class="flex items-center gap-2 min-w-0">
            <span class="font-mono font-black text-sm leading-none shrink-0"
                  :style="{ color: runsColor, minWidth: '14px', textAlign: 'right' }">
                {{ runs }}
            </span>
            <div v-if="!isAlt"
                 class="flex-1 h-[4px] rounded-full overflow-hidden"
                 style="background: rgba(255,255,255,0.05); min-width: 36px;">
                <div class="h-full rounded-full"
                     :style="{ width: runsBarPct + '%', background: runsColor, boxShadow: `0 0 4px ${runsColor}77` }">
                </div>
            </div>
        </div>
    </td>

    <!-- M+ Rating -->
    <td :class="[rh, isAlt ? 'px-1 py-0.5' : 'p-2.5', 'text-center font-mono font-bold border-l border-white/[0.06]']"
        :style="{ color: ratingColor }">
        {{ char?.mythic_rating != null ? Math.round(char.mythic_rating) : '—' }}
    </td>
</template>
