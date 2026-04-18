<script setup>
import { computed } from 'vue';
import GlassModal from '../UI/GlassModal.vue';

const props = defineProps({
    show: { type: Boolean, required: true },
    character: { type: Object, default: null },
    allStaticAlts: { type: Object, default: () => ({}) },
    weeklyRaidData: { type: Object, default: () => ({}) },
    benchHistory: { type: Object, default: () => ({}) },
    planningStats: { type: Object, default: () => ({}) },
    encounters: { type: Array, default: () => [] },
    difficulty: { type: String, default: 'mythic' },
    canManage: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'swap-character', 'change-spec']);

const userAlts = computed(() => {
    if (!props.character) return [];
    const userId = props.character.user_id;
    return props.allStaticAlts[userId] || [];
});

// All specs for the current character (from allStaticAlts data)
const currentCharSpecs = computed(() => {
    if (!props.character) return [];
    const userId = props.character.user_id;
    const alts = props.allStaticAlts[userId] || [];
    const thisChar = alts.find(a => a.id === props.character.id);
    return thisChar?.specs || [];
});

const currentCharLockouts = computed(() => {
    if (!props.character) return [];
    const raids = props.weeklyRaidData[props.character.id];
    if (!raids) return [];

    const diffKey = { mythic: 'M', heroic: 'H', normal: 'N', raid_finder: 'LFR' }[props.difficulty] || 'M';
    const result = [];
    for (const [instance, bosses] of Object.entries(raids)) {
        for (const boss of bosses) {
            result.push({
                instance,
                name: boss.name,
                killed: boss[diffKey] ?? false,
                killedLower: boss.H || boss.N || boss.LFR || false,
            });
        }
    }
    return result;
});

const charBenchHistory = computed(() => {
    if (!props.character) return null;
    return props.benchHistory[props.character.id] || null;
});

const charAttendance = computed(() => {
    if (!props.character) return null;
    return props.planningStats[props.character.id] || null;
});

const diffLabel = { mythic: 'Mythic', heroic: 'Heroic', normal: 'Normal', raid_finder: 'LFR' };

const countKills = (charId) => {
    const raids = props.weeklyRaidData[charId];
    if (!raids) return 0;
    const diffKey = { mythic: 'M', heroic: 'H', normal: 'N', raid_finder: 'LFR' }[props.difficulty] || 'M';
    let count = 0;
    for (const bosses of Object.values(raids)) {
        for (const boss of bosses) {
            if (boss[diffKey]) count++;
        }
    }
    return count;
};
</script>

<template>
    <GlassModal :show="show" max-width="max-w-lg" @close="emit('close')">
        <div v-if="character" class="p-5">
            <!-- Header -->
            <div class="flex items-center gap-3 mb-4">
                <img :src="character.avatar_url" class="w-12 h-12 rounded-lg border border-white/10 object-cover">
                <div>
                    <div
                        class="text-sm font-bold"
                        :class="'text-wow-' + (character.playable_class || '').toLowerCase().replace(/ /g, '-')"
                    >{{ character.name }}</div>
                    <div class="text-3xs text-on-surface-variant flex items-center gap-2">
                        <span v-if="character.main_spec?.name">{{ character.main_spec.name }}</span>
                        <span v-if="character.equipped_item_level || character.item_level" class="font-bold">
                            {{ character.equipped_item_level || character.item_level }} ilvl
                        </span>
                    </div>
                </div>

                <!-- Close -->
                <button @click="emit('close')" class="ml-auto text-on-surface-variant hover:text-white">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <!-- Stats row -->
            <div class="flex gap-3 mb-4">
                <div v-if="charAttendance" class="bg-white/5 rounded-lg px-3 py-2 text-center">
                    <div class="text-xs font-black text-fuchsia-400">{{ charAttendance.percentage }}%</div>
                    <div class="text-5xs text-on-surface-variant uppercase tracking-wider">Attendance</div>
                </div>
                <div v-if="charBenchHistory" class="bg-orange-500/10 border border-orange-500/20 rounded-lg px-3 py-2 text-center">
                    <div class="text-xs font-black text-orange-400">{{ charBenchHistory.bench_count }}/{{ charBenchHistory.total_events }}</div>
                    <div class="text-5xs text-orange-400/70 uppercase tracking-wider">Benched recently</div>
                </div>
            </div>

            <!-- Spec selector (RL only, when character has multiple specs) -->
            <div v-if="canManage && currentCharSpecs.length > 1" class="mb-4">
                <div class="text-4xs font-black uppercase tracking-wider text-on-surface-variant/50 mb-2">
                    Specialization
                </div>
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="spec in currentCharSpecs"
                        :key="spec.id"
                        @click="spec.id !== character.main_spec?.id && emit('change-spec', { characterId: character.id, spec })"
                        class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border transition-all text-left"
                        :class="spec.id === character.main_spec?.id
                            ? 'border-fuchsia-400/30 bg-fuchsia-400/10'
                            : 'border-white/10 hover:border-white/20 hover:bg-white/5'"
                    >
                        <img
                            v-if="spec.icon_url"
                            :src="spec.icon_url"
                            class="w-5 h-5 rounded"
                        >
                        <div>
                            <div class="text-3xs font-semibold text-white">{{ spec.name }}</div>
                            <div class="text-5xs text-on-surface-variant uppercase">{{ spec.role }}</div>
                        </div>
                        <span
                            v-if="spec.id === character.main_spec?.id"
                            class="material-symbols-outlined text-fuchsia-400 text-xs"
                        >check_circle</span>
                    </button>
                </div>
            </div>

            <!-- Lockout status -->
            <div v-if="currentCharLockouts.length" class="mb-4">
                <div class="text-4xs font-black uppercase tracking-wider text-on-surface-variant/50 mb-2">
                    Weekly Lockout ({{ diffLabel[difficulty] || difficulty }})
                </div>
                <div class="grid grid-cols-3 gap-1">
                    <div
                        v-for="boss in currentCharLockouts"
                        :key="boss.name"
                        class="flex items-center gap-1 px-2 py-1 rounded text-4xs border"
                        :class="boss.killed
                            ? 'bg-red-500/10 border-red-500/20 text-red-400'
                            : 'bg-green-500/10 border-green-500/20 text-green-400'"
                    >
                        <span class="material-symbols-outlined text-3xs">{{ boss.killed ? 'lock' : 'lock_open' }}</span>
                        <span class="truncate font-bold">{{ boss.name }}</span>
                    </div>
                </div>
            </div>

            <!-- Alt swap (only for managers) -->
            <div v-if="canManage && userAlts.length > 1" class="border-t border-white/10 pt-4">
                <div class="text-4xs font-black uppercase tracking-wider text-on-surface-variant/50 mb-2">
                    Available Characters
                </div>
                <div class="space-y-1">
                    <button
                        v-for="alt in userAlts"
                        :key="alt.id"
                        @click="alt.id !== character.id && emit('swap-character', { fromCharId: character.id, toCharId: alt.id, userId: character.user_id })"
                        class="w-full flex items-center gap-3 px-3 py-2 rounded-lg border transition-all text-left"
                        :class="alt.id === character.id
                            ? 'border-fuchsia-400/30 bg-fuchsia-400/10'
                            : 'border-white/5 hover:border-white/20 hover:bg-white/5'"
                    >
                        <img
                            v-if="alt.avatar_url"
                            :src="alt.avatar_url"
                            class="w-8 h-8 rounded border border-white/10 object-cover"
                        >
                        <div v-else class="w-8 h-8 rounded border border-white/10 bg-white/5 flex items-center justify-center">
                            <span class="material-symbols-outlined text-sm opacity-40">person</span>
                        </div>

                        <div class="flex-1 min-w-0">
                            <div
                                class="text-2xs font-bold"
                                :class="'text-wow-' + (alt.playable_class || '').toLowerCase().replace(/ /g, '-')"
                            >{{ alt.name }}</div>
                            <div class="text-4xs text-on-surface-variant flex items-center gap-1.5">
                                <span v-if="alt.main_spec?.name">{{ alt.main_spec.name }}</span>
                                <span class="font-bold">{{ alt.item_level }} ilvl</span>
                                <span
                                    v-if="alt.role === 'main'"
                                    class="text-5xs font-black uppercase bg-fuchsia-400/20 text-fuchsia-400 px-1 rounded"
                                >main</span>
                            </div>
                        </div>

                        <!-- Alt lockout summary -->
                        <div v-if="weeklyRaidData[alt.id]" class="text-4xs text-on-surface-variant/50 font-semibold shrink-0">
                            {{ countKills(alt.id) }}/{{ currentCharLockouts.length }}
                        </div>

                        <span
                            v-if="alt.id === character.id"
                            class="material-symbols-outlined text-fuchsia-400 text-sm"
                        >check_circle</span>
                    </button>
                </div>
            </div>
        </div>
    </GlassModal>
</template>

<style scoped>
.text-wow-warrior      { color: #C69B6D; }
.text-wow-paladin      { color: #F48CBA; }
.text-wow-hunter       { color: #ABD473; }
.text-wow-rogue        { color: #FFF468; }
.text-wow-priest       { color: #FFFFFF; }
.text-wow-death-knight { color: #C41F3B; }
.text-wow-shaman       { color: #0070DD; }
.text-wow-mage         { color: #3FC7EB; }
.text-wow-warlock      { color: #8788EE; }
.text-wow-monk         { color: #00FF98; }
.text-wow-druid        { color: #FF7C0A; }
.text-wow-demon-hunter { color: #A330C9; }
.text-wow-evoker       { color: #33937F; }
</style>
