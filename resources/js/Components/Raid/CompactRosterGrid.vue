<script setup>
import { computed } from 'vue';
import { useWowClasses } from '../../composables/useWowClasses.js';
import { useTranslation } from '@/composables/useTranslation';

const { getClassColor, getSpecName } = useWowClasses();
const { __ } = useTranslation();

const props = defineProps({
    mainRoster: { type: Object, required: true },
    absentRoster: { type: Array, default: () => [] },
    roleLimits: { type: Object, default: () => ({}) },
    weeklyRaidData: { type: Object, default: () => ({}) },
    benchHistory: { type: Object, default: () => ({}) },
    planningStats: { type: Object, default: () => ({}) },
    encounterSlug: { type: String, default: null },
    difficulty: { type: String, default: 'mythic' },
    splitEnabled: { type: Boolean, default: false },
    splitCount: { type: Number, default: 1 },
    activeSplit: { type: Number, default: null },
    canManage: { type: Boolean, default: false },
    locked: { type: Boolean, default: false },
});

const emit = defineEmits(['character-click', 'assign-split', 'bench', 'unbench']);

const splitLabels = ['A', 'B', 'C', 'D'];

const roles = {
    tank: { label: 'Tanks', icon: 'tank.svg', color: 'text-blue-400', border: 'border-blue-400/30' },
    heal: { label: 'Healers', icon: 'heal.svg', color: 'text-green-500', border: 'border-green-500/30' },
    mdps: { label: 'Melee', icon: 'melee.svg', color: 'text-red-500', border: 'border-red-500/30' },
    rdps: { label: 'Ranged', icon: 'range.svg', color: 'text-red-500', border: 'border-red-500/30' },
};

const statusIcons = {
    present: { icon: 'check', class: 'text-green-400' },
    late: { icon: 'schedule', class: 'text-yellow-400' },
    pending: { icon: 'help', class: 'text-white/30' },
    tentative: { icon: 'help', class: 'text-yellow-500' },
    absent: { icon: 'close', class: 'text-red-400' },
};

const roleLimit = (roleKey) => {
    if (roleKey === 'tank') return props.roleLimits.tank ?? 2;
    if (roleKey === 'heal') return props.roleLimits.heal?.max ?? 4;
    return null; // DPS fills remaining
};

const layoutData = computed(() => {
    const data = {};
    const statusWeights = { present: 1, late: 2, pending: 3, tentative: 4, absent: 5 };
    const getWeight = (s) => statusWeights[s] || 99;

    for (const roleKey of ['tank', 'heal', 'mdps', 'rdps']) {
        const mainChars = [...(props.mainRoster[roleKey] || [])].sort(
            (a, b) => getWeight(a.pivot?.status) - getWeight(b.pivot?.status)
        );
        const absentChars = props.absentRoster
            .filter(c => c.assigned_role === roleKey)
            .sort((a, b) => getWeight(a.pivot?.status) - getWeight(b.pivot?.status));

        data[roleKey] = { mainChars, absentChars };
    }
    return data;
});

const totalPresent = computed(() => {
    let count = 0;
    for (const roleKey of ['tank', 'heal', 'mdps', 'rdps']) {
        count += layoutData.value[roleKey].mainChars.length;
    }
    return count;
});

const totalLimit = computed(() => props.roleLimits.total ?? 20);

const isBenched = (charId) => {
    const h = props.benchHistory[charId];
    return h && h.bench_count >= 2;
};

const getBossLockout = (charId) => {
    if (!props.encounterSlug || !props.weeklyRaidData[charId]) return null;
    const raids = props.weeklyRaidData[charId];
    for (const instance of Object.values(raids)) {
        for (const boss of instance) {
            const slug = boss.name?.toLowerCase().replace(/['']/g, '').replace(/&/g, '').replace(/,/g, '').replace(/\s+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
            if (slug === props.encounterSlug) {
                const diffKey = { mythic: 'M', heroic: 'H', normal: 'N', raid_finder: 'LFR' }[props.difficulty] || 'M';
                return boss[diffKey] ? 'locked' : null;
            }
        }
    }
    return null;
};

const getAttendance = (charId) => {
    const stats = props.planningStats[charId];
    if (!stats) return null;
    return stats.percentage;
};
</script>

<template>
    <div class="space-y-2">
        <!-- Total count -->
        <div class="flex items-center justify-between px-1">
            <span class="text-4xs font-bold uppercase tracking-wider text-on-surface-variant/50">{{ __('Roster') }}</span>
            <span class="text-3xs font-bold" :class="totalPresent >= totalLimit ? 'text-green-400' : 'text-on-surface-variant'">
                {{ totalPresent }} / {{ totalLimit }}
            </span>
        </div>

        <!-- Role sections -->
        <div
            v-for="roleKey in ['tank', 'heal', 'mdps', 'rdps']"
            :key="roleKey"
            class="bg-surface-container/40 border border-white/5 rounded-lg overflow-hidden"
        >
            <!-- Role header -->
            <div class="flex items-center justify-between px-3 py-1.5 border-b border-white/5">
                <div class="flex items-center gap-1.5">
                    <img :src="'/images/roles/' + roles[roleKey].icon" class="w-3.5 h-3.5 opacity-70">
                    <span class="text-4xs font-bold uppercase tracking-wider" :class="roles[roleKey].color">
                        {{ roles[roleKey].label }}
                    </span>
                </div>
                <span class="text-4xs font-semibold text-on-surface-variant/60">
                    {{ layoutData[roleKey].mainChars.length }}
                    <template v-if="roleLimit(roleKey)">/ {{ roleLimit(roleKey) }}</template>
                </span>
            </div>

            <!-- Characters -->
            <div class="px-1.5 py-1 flex flex-wrap gap-0.5">
                <!-- Main roster characters -->
                <button
                    v-for="char in layoutData[roleKey].mainChars"
                    :key="char.id"
                    @click="emit('character-click', char)"
                    class="flex items-center gap-1.5 px-2 py-1 rounded-md hover:bg-white/10 transition-colors group relative"
                    :class="[
                        char.pivot?.status === 'pending' ? 'opacity-40' : '',
                        getBossLockout(char.id) ? 'ring-1 ring-yellow-500/40' : '',
                    ]"
                    :title="char.name + ' — ' + getSpecName(char)"
                >
                    <!-- Spec icon -->
                    <div class="relative shrink-0">
                        <img
                            v-if="char.main_spec?.icon_url"
                            :src="char.main_spec.icon_url"
                            class="w-6 h-6 rounded border border-white/10"
                        >
                        <div v-else class="w-6 h-6 rounded border border-white/10 flex items-center justify-center bg-white/5">
                            <span class="material-symbols-outlined text-xs opacity-40">person</span>
                        </div>

                        <!-- Status dot -->
                        <div
                            class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 rounded-full border border-surface-container flex items-center justify-center"
                            :class="{
                                'bg-green-400': char.pivot?.status === 'present',
                                'bg-yellow-400': char.pivot?.status === 'late',
                                'bg-white/30': char.pivot?.status === 'pending',
                            }"
                        ></div>
                    </div>

                    <!-- Name -->
                    <span
                        class="text-3xs font-semibold leading-tight truncate max-w-[70px]"
                        :class="'text-wow-' + (char.playable_class || '').toLowerCase().replace(/ /g, '-')"
                    >{{ char.name }}</span>

                    <!-- Lockout badge -->
                    <span
                        v-if="getBossLockout(char.id)"
                        class="material-symbols-outlined text-3xs text-yellow-500"
                        :title="__('Already killed this boss')"
                    >lock</span>

                    <!-- Comment indicator -->
                    <span
                        v-if="char.pivot?.comment"
                        class="material-symbols-outlined text-3xs text-yellow-400/70"
                    >chat_bubble</span>

                    <!-- Bench button (RL only, not when locked) -->
                    <button
                        v-if="canManage && !locked"
                        @click.stop="emit('bench', char.id)"
                        class="hidden group-hover:flex items-center justify-center w-4 h-4 rounded bg-red-500/20 border border-red-500/30 hover:bg-red-500/30 transition-all"
                        :title="__('Move to bench')"
                    >
                        <span class="material-symbols-outlined text-2xs text-red-400">arrow_downward</span>
                    </button>

                    <!-- Split badge + assign -->
                    <template v-if="splitEnabled">
                        <span
                            v-if="char.pivot?.split_group"
                            class="text-5xs font-bold px-1 rounded bg-fuchsia-400/20 text-fuchsia-400 leading-none"
                        >{{ splitLabels[(char.pivot.split_group || 1) - 1] }}</span>
                        <select
                            v-if="activeSplit === null"
                            @click.stop
                            @change.stop="emit('assign-split', char.id, Number($event.target.value) || null)"
                            class="opacity-0 group-hover:opacity-100 w-5 h-5 bg-transparent text-5xs cursor-pointer appearance-none text-center text-fuchsia-400"
                            :value="char.pivot?.split_group || ''"
                            :title="__('Assign split')"
                        >
                            <option value="">—</option>
                            <option v-for="n in splitCount" :key="n" :value="n">{{ splitLabels[n - 1] }}</option>
                        </select>
                    </template>
                </button>

                <!-- Empty state -->
                <div
                    v-if="layoutData[roleKey].mainChars.length === 0 && layoutData[roleKey].absentChars.length === 0"
                    class="text-4xs text-on-surface-variant/30 px-2 py-1 italic"
                >{{ __('No players') }}</div>
            </div>

            <!-- Absent/tentative -->
            <div
                v-if="layoutData[roleKey].absentChars.length > 0"
                class="px-1.5 pb-1 flex flex-wrap gap-0.5 border-t border-white/5 pt-1"
            >
                <button
                    v-for="char in layoutData[roleKey].absentChars"
                    :key="'abs-' + char.id"
                    @click="emit('character-click', char)"
                    class="flex items-center gap-1.5 px-2 py-1 rounded-md hover:bg-white/10 transition-colors group/abs"
                    :class="[
                        char.pivot?.status === 'tentative' ? 'opacity-50' : 'opacity-30',
                        isBenched(char.id) ? '!opacity-70 ring-1 ring-orange-500/40' : '',
                    ]"
                >
                    <img
                        v-if="char.main_spec?.icon_url"
                        :src="char.main_spec.icon_url"
                        class="w-5 h-5 rounded border border-white/10 grayscale"
                    >
                    <div v-else class="w-5 h-5 rounded border border-white/10 flex items-center justify-center bg-white/5 grayscale">
                        <span class="material-symbols-outlined text-3xs opacity-40">person</span>
                    </div>

                    <span class="text-4xs font-semibold truncate max-w-[60px]"
                        :class="isBenched(char.id) ? 'text-orange-400' : 'text-on-surface-variant/70'"
                    >{{ char.name }}</span>

                    <!-- Bench warning badge -->
                    <span
                        v-if="isBenched(char.id)"
                        class="text-5xs font-bold px-1 rounded bg-orange-500/20 text-orange-400 leading-none whitespace-nowrap"
                    >{{ benchHistory[char.id]?.bench_count }}/{{ benchHistory[char.id]?.total_events }}</span>

                    <button
                        v-if="canManage && !locked"
                        @click.stop="emit('unbench', char.id)"
                        class="items-center justify-center w-4 h-4 rounded border transition-all"
                        :class="isBenched(char.id)
                            ? 'flex bg-green-500/20 border-green-500/30 hover:bg-green-500/30'
                            : 'hidden group-hover/abs:flex bg-green-500/20 border-green-500/30 hover:bg-green-500/30'"
                        :title="isBenched(char.id) ? __('Benched frequently! Move to roster') : __('Move to roster')"
                    >
                        <span class="material-symbols-outlined text-2xs text-green-400">arrow_upward</span>
                    </button>

                    <span v-if="!canManage" class="material-symbols-outlined text-3xs" :class="statusIcons[char.pivot?.status]?.class">
                        {{ statusIcons[char.pivot?.status]?.icon }}
                    </span>
                </button>
            </div>
        </div>
    </div>
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
