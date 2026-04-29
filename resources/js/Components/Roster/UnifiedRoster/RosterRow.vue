<script setup>
import { computed, inject, ref, getCurrentInstance } from 'vue';
import SummaryCells from './Cells/SummaryCells.vue';
import RaidCells from './Cells/RaidCells.vue';
import GearCells from './Cells/GearCells.vue';
import VaultCells from './Cells/VaultCells.vue';
import GearSpecSwitcher from '@/Components/Roster/GearSpecSwitcher.vue';

const { proxy } = getCurrentInstance();
const __ = (key, replace = {}) => proxy.__(key, replace);

// StatusPill config — labels, icons, colours per value
const STATUS_CONFIG = {
    core:  { label: 'ОСНОВНИЙ', icon: 'check',   color: '#39FF14' },
    bench: { label: 'БЕНЧ',     icon: 'pause',   color: '#ff6e84' },
};
const ROLE_CONFIG = {
    leader:  { label: 'ЛІДЕР',   icon: 'star',          color: '#fcf266' },
    officer: { label: 'ОФІЦЕР',  icon: 'shield_person', color: '#fcf266' },
    member:  { label: 'УЧАСНИК', icon: 'person',         color: '#767577' },
};

const statusOpen = ref(false);
const roleOpen   = ref(false);

const props = defineProps({
    member: { type: Object, required: true },
    char: { type: Object, required: true }, // can be main_character or an alt
    groupedRoster: { type: Object, required: true },
    isAlt: { type: Boolean, default: false },
    activeTab: { type: String, required: true },
    selectedDifficulty: { type: String, required: true },
    classColors: { type: Object, required: true },
    tierColors: { type: Object, required: true },
    killMarkClass: { type: [String, Object], required: true },
    raidColumns: { type: Array, required: true },
    expanded: { type: Boolean, default: false },
    canManageStatus: { type: Boolean, default: false },
    canManageAccess: { type: Boolean, default: false },
    canKick: { type: Boolean, default: false },
    roleIconSrc: { type: Function, required: true },
    tierCount: { type: Function, required: true },
    hasAuditIssues: { type: Function, required: true },
    auditTitle: { type: Function, required: true },
    compareMode: { type: Boolean, default: false },
    compareSelected: { type: Boolean, default: false },
    compareIsolated: { type: Boolean, default: false },
});

const rowHeights = inject('rowHeights');
const rh = computed(() => props.isAlt ? rowHeights.alt : rowHeights.main);

const { getSpecData, selectSpec, getSpecOptions, getActiveSpec } = inject('specSwitch');

const CLASS_HEX = {
    'Death Knight': '#C41F3B', 'Demon Hunter': '#A330C9', 'Druid': '#FF7C0A',
    'Evoker': '#33937F',       'Hunter': '#ABD473',       'Mage': '#3FC7EB',
    'Monk': '#00FF98',         'Paladin': '#F48CBA',      'Priest': '#FFFFFF',
    'Rogue': '#FFF468',        'Shaman': '#0070DD',       'Warlock': '#8788EE',
    'Warrior': '#C69B6D',
};
const charClassColor = computed(() => CLASS_HEX[props.char?.class] ?? 'rgba(255,255,255,0.15)');
const effectiveChar = computed(() => getSpecData(props.char));

/** The spec object for the currently displayed spec (for icon). */
const currentSpecObj = computed(() => {
    const active = getActiveSpec(props.char);
    if (!active) {
        // Fallback to main_spec
        return props.char?.main_spec ? {
            name: props.char.main_spec.name,
            icon_url: props.char.main_spec.icon_url,
        } : null;
    }
    const allSpecs = props.char?.all_specs ?? [];
    return allSpecs.find(s => s.name === active) ?? { name: active, icon_url: props.char?.main_spec?.icon_url };
});

const emit = defineEmits([
    'toggle-expand',
    'update-access-role',
    'update-roster-status',
    'kick-member',
    'open-audit-modal',
    'toggle-compare'
]);
</script>

<template>
    <tr :class="[
            isAlt ? 'text-2xs' : 'transition-colors group/row',
            !isAlt && expanded ? 'bg-[#39FF14]/[0.04]' : '',
            !isAlt && compareMode && compareSelected && !compareIsolated ? '!bg-[#39FF14]/[0.08]' : '',
        ]"
        :style="[
            isAlt ? { borderBottom: '1px solid rgba(255,255,255,0.04)', background: 'rgba(0,0,0,0.25)' } : { borderBottom: '1px solid rgba(255,255,255,0.06)' },
            !isAlt && compareMode && compareSelected ? { borderLeft: '2px solid #39FF14' } : {},
        ]">

        <!-- Compare checkbox column -->
        <td :class="[rh, 'compare-col text-center align-middle', compareMode ? 'compare-col-open' : 'compare-col-closed']">
            <label v-if="!isAlt" class="compare-col-content inline-flex items-center justify-center cursor-pointer select-none">
                <input
                    type="checkbox"
                    :checked="compareSelected"
                    @change="emit('toggle-compare')"
                    class="peer sr-only"
                />
                <span class="w-4 h-4 rounded border border-white/20 bg-white/5 flex items-center justify-center transition-all peer-checked:bg-[#39FF14] peer-checked:border-[#39FF14] peer-hover:border-[#39FF14]/60">
                    <span v-if="compareSelected" class="material-symbols-outlined text-gray-900 text-sm leading-none">check</span>
                </span>
            </label>
        </td>

        <!-- Character identity -->
        <td :class="[rh, isAlt ? 'pl-5' : 'p-2']">
            <div :class="[
                'flex items-center justify-between h-full',
                isAlt ? 'px-2' : 'pl-4',
            ]">
                <div class="flex items-center gap-2 min-w-0">
                    <div class="relative shrink-0">
                        <img v-if="char?.avatar_url"
                             :src="char.avatar_url"
                             :class="isAlt ? 'w-5 h-5 rounded' : 'w-8 h-8 rounded-lg'"
                             :style="{ border: `1.5px solid ${charClassColor}66` }"
                             :alt="char.name">
                        <div v-else
                             :class="isAlt ? 'w-5 h-5 rounded flex items-center justify-center' : 'w-8 h-8 rounded-lg flex items-center justify-center'"
                             :style="{ background: charClassColor + '18', border: `1.5px solid ${charClassColor}55` }">
                            <span class="font-black select-none"
                                  :class="isAlt ? 'text-[9px]' : 'text-xs'"
                                  :style="{ color: charClassColor }">
                                {{ char?.name?.charAt(0)?.toUpperCase() ?? '?' }}
                            </span>
                        </div>
                        <!-- Bench ribbon -->
                        <div v-if="!isAlt && member.roster_status === 'bench'"
                             class="absolute -top-1 -left-1 px-1 rounded font-black uppercase leading-tight"
                             style="background: #ff6e84; color: #fff; font-size: 6px; letter-spacing: 0.06em; transform: rotate(-6deg);">
                            {{ __('Bench') }}
                        </div>
                        <!-- Officer star -->
                        <div v-if="!isAlt && (member.access_role === 'officer' || member.access_role === 'leader')"
                             class="absolute -bottom-1 -right-1 w-[13px] h-[13px] rounded-full flex items-center justify-center font-black"
                             style="background: #fcf266; border: 1.5px solid #0e0e10; font-size: 7px; color: #1a1a00;">
                            ★
                        </div>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-1">
                            <span class="font-bold tracking-tight truncate"
                                  :class="[classColors[char?.class] ?? 'text-white', isAlt ? 'text-xs' : 'text-base']">
                                {{ char?.name || (isAlt ? __('Unknown') : member.name) }}
                            </span>
                            <span v-if="isAlt" class="text-5xs text-on-surface-variant font-semibold uppercase">{{ char?.class ?? '' }}</span>

                            <button v-if="!isAlt && (member.alts || []).length > 0"
                                    @click="emit('toggle-expand', member.id)"
                                    class="text-on-surface-variant hover:text-white transition-colors">
                                <span class="material-symbols-outlined text-3xs">
                                    {{ expanded ? 'expand_less' : 'expand_more' }}
                                </span>
                            </button>
                        </div>
                        <div v-if="!isAlt && member.name !== char?.name" class="text-4xs text-on-surface-variant font-medium uppercase tracking-tighter truncate leading-tight">
                            {{ member.name }}
                        </div>
                    </div>
                </div>

                <!-- Spec switcher (right-aligned) -->
                    <GearSpecSwitcher
                        :availableSpecs="getSpecOptions(char)"
                        :activeSpec="getActiveSpec(char)"
                        :mainSpecName="char?.main_spec?.name"
                        :currentSpec="currentSpecObj"
                        :size="isAlt ? 'xs' : 'sm'"
                        @select="selectSpec(char?.id, $event)"
                    />
            </div>
        </td>

        <!-- Summary Tab Cells -->
        <template v-if="activeTab === 'summary'">
            <SummaryCells :char="effectiveChar"
                          :tier-colors="tierColors"
                          :tier-count="tierCount"
                          :is-alt="isAlt" />
        </template>

        <!-- Raid Tab Cells -->
        <template v-if="activeTab === 'raids'">
            <RaidCells :char="effectiveChar"
                       :grouped-roster="groupedRoster"
                       :raid-columns="raidColumns"
                       :selected-difficulty="selectedDifficulty"
                       :kill-mark-class="killMarkClass"
                       :is-alt="isAlt" />
        </template>

        <!-- Gear Tab Cells -->
        <template v-if="activeTab === 'gear'">
            <GearCells :char="effectiveChar"
                       :is-alt="isAlt"
                       :has-audit-issues="hasAuditIssues"
                       :audit-title="auditTitle"
                       @audit-click="emit('open-audit-modal', effectiveChar)" />
        </template>

        <!-- Vault Tab Cells -->
        <template v-if="activeTab === 'vault'">
            <VaultCells :char="effectiveChar" :is-alt="isAlt" />
        </template>

        <!-- Audit badge (summary only) -->
        <template v-if="activeTab === 'summary'">
            <td :class="[rh, isAlt ? 'px-1 py-0.5' : 'p-2.5', 'text-center border-l border-white/[0.06]']">
                <button v-if="hasAuditIssues(effectiveChar)"
                        @click="emit('open-audit-modal', effectiveChar)"
                        class="inline-flex items-center gap-1 font-bold uppercase tracking-[0.06em] rounded-md transition-all cursor-pointer hover:opacity-80"
                        :class="isAlt ? 'text-[9px] px-1 py-0.5' : 'text-[10px] px-2 py-1'"
                        style="background: rgba(255,110,132,0.10); border: 1px solid rgba(255,110,132,0.4); color: #ff6e84;"
                        :title="auditTitle(effectiveChar)">
                    <span class="material-symbols-outlined leading-none" :class="isAlt ? 'text-xs' : 'text-sm'">warning</span>
                    {{ (effectiveChar.missing_enchants_slots?.length ?? 0) + (effectiveChar.low_quality_enchants_slots?.length ?? 0) + (effectiveChar.empty_sockets_count ?? 0) }}
                </button>
                <span v-else
                      class="inline-flex items-center gap-1 font-bold uppercase tracking-[0.06em]"
                      :class="isAlt ? 'text-[9px]' : 'text-[10px]'"
                      style="color: rgba(57,255,20,0.65);">
                    <span class="material-symbols-outlined leading-none" :class="isAlt ? 'text-xs' : 'text-sm'">check_circle</span>
                    <span v-if="!isAlt">ALL CLEAR</span>
                </span>
            </td>

            <!-- Role / Status pills (summary only, main character only) -->
            <template v-if="!isAlt">

                <!-- Roster Status -->
                <td :class="[rh, 'px-2 w-[130px] border-l border-white/5 text-center']">
                    <!-- Editable: pill button + dropdown -->
                    <div v-if="canManageStatus" class="relative flex justify-center">
                        <button
                            @click="statusOpen = !statusOpen; roleOpen = false"
                            class="inline-flex items-center gap-1.5 px-2 py-1 rounded cursor-pointer transition-opacity hover:opacity-80"
                            style="font-size:9px; font-weight:800; letter-spacing:0.08em; font-family:Inter; line-height:1.2;"
                            :style="{
                                border: `1px solid ${(STATUS_CONFIG[member.roster_status]?.color ?? '#767577')}55`,
                                color:  STATUS_CONFIG[member.roster_status]?.color ?? '#767577',
                                background: 'transparent',
                                borderRadius: '6px',
                                width: '108px',
                            }">
                            <span class="material-symbols-outlined" style="font-size:11px;">{{ STATUS_CONFIG[member.roster_status]?.icon ?? 'circle' }}</span>
                            <span class="flex-1 text-center">{{ STATUS_CONFIG[member.roster_status]?.label ?? member.roster_status?.toUpperCase() }}</span>
                            <span class="material-symbols-outlined" style="font-size:11px; opacity:0.5;">expand_more</span>
                        </button>
                        <!-- Backdrop -->
                        <div v-if="statusOpen" class="fixed inset-0 z-40" @click="statusOpen = false"/>
                        <!-- Dropdown -->
                        <div v-if="statusOpen"
                             class="absolute top-full left-1/2 -translate-x-1/2 mt-1 z-50 rounded-lg overflow-hidden shadow-2xl border border-white/10"
                             style="background:#1a1a1d; min-width:120px;">
                            <button v-for="opt in [{ id:'core', cfg: STATUS_CONFIG.core }, { id:'bench', cfg: STATUS_CONFIG.bench }]"
                                    :key="opt.id"
                                    @click="emit('update-roster-status', member, opt.id); statusOpen = false"
                                    class="w-full flex items-center gap-2 px-3 py-2 text-left transition-colors hover:bg-white/5"
                                    style="font-size:10px; font-weight:700; font-family:Inter;"
                                    :style="{ color: opt.cfg.color }">
                                <span class="material-symbols-outlined" style="font-size:12px;">{{ opt.cfg.icon }}</span>
                                {{ opt.cfg.label }}
                            </button>
                        </div>
                    </div>
                    <!-- Static: same visual width, no interaction -->
                    <div v-else class="flex justify-center">
                        <span class="inline-flex items-center justify-center gap-1.5 px-2 py-1"
                              style="font-size:9px; font-weight:800; letter-spacing:0.08em; font-family:Inter; line-height:1.2; width:108px; border-radius:6px;"
                              :style="{ color: STATUS_CONFIG[member.roster_status]?.color ?? '#767577' }">
                            <span class="material-symbols-outlined" style="font-size:11px;">{{ STATUS_CONFIG[member.roster_status]?.icon ?? 'circle' }}</span>
                            <span>{{ STATUS_CONFIG[member.roster_status]?.label ?? member.roster_status?.toUpperCase() }}</span>
                        </span>
                    </div>
                </td>

                <!-- Access Role -->
                <td :class="[rh, 'px-2 w-[130px] border-l border-white/5 text-center']">
                    <!-- Editable: pill button + dropdown -->
                    <div v-if="canManageAccess && member.access_role !== 'leader'" class="relative flex justify-center">
                        <button
                            @click="roleOpen = !roleOpen; statusOpen = false"
                            class="inline-flex items-center gap-1.5 px-2 py-1 rounded cursor-pointer transition-opacity hover:opacity-80"
                            style="font-size:9px; font-weight:800; letter-spacing:0.08em; font-family:Inter; line-height:1.2;"
                            :style="{
                                border: `1px solid ${(ROLE_CONFIG[member.access_role]?.color ?? '#767577')}55`,
                                color:  ROLE_CONFIG[member.access_role]?.color ?? '#767577',
                                background: 'transparent',
                                borderRadius: '6px',
                                width: '108px',
                            }">
                            <span class="material-symbols-outlined" style="font-size:11px;">{{ ROLE_CONFIG[member.access_role]?.icon ?? 'person' }}</span>
                            <span class="flex-1 text-center">{{ ROLE_CONFIG[member.access_role]?.label ?? member.access_role?.toUpperCase() }}</span>
                            <span class="material-symbols-outlined" style="font-size:11px; opacity:0.5;">expand_more</span>
                        </button>
                        <!-- Backdrop -->
                        <div v-if="roleOpen" class="fixed inset-0 z-40" @click="roleOpen = false"/>
                        <!-- Dropdown -->
                        <div v-if="roleOpen"
                             class="absolute top-full left-1/2 -translate-x-1/2 mt-1 z-50 rounded-lg overflow-hidden shadow-2xl border border-white/10"
                             style="background:#1a1a1d; min-width:120px;">
                            <button v-for="opt in [{ id:'officer', cfg: ROLE_CONFIG.officer }, { id:'member', cfg: ROLE_CONFIG.member }]"
                                    :key="opt.id"
                                    @click="emit('update-access-role', member, opt.id); roleOpen = false"
                                    class="w-full flex items-center gap-2 px-3 py-2 text-left transition-colors hover:bg-white/5"
                                    style="font-size:10px; font-weight:700; font-family:Inter;"
                                    :style="{ color: opt.cfg.color }">
                                <span class="material-symbols-outlined" style="font-size:12px;">{{ opt.cfg.icon }}</span>
                                {{ opt.cfg.label }}
                            </button>
                        </div>
                    </div>
                    <!-- Static: leader or non-manager -->
                    <div v-else class="flex justify-center">
                        <span class="inline-flex items-center justify-center gap-1.5 px-2 py-1"
                              style="font-size:9px; font-weight:800; letter-spacing:0.08em; font-family:Inter; line-height:1.2; width:108px; border-radius:6px;"
                              :style="{ color: ROLE_CONFIG[member.access_role]?.color ?? '#767577' }">
                            <span class="material-symbols-outlined" style="font-size:11px;">{{ ROLE_CONFIG[member.access_role]?.icon ?? 'person' }}</span>
                            <span>{{ ROLE_CONFIG[member.access_role]?.label ?? member.access_role?.toUpperCase() }}</span>
                        </span>
                    </div>
                </td>

                <!-- Kick -->
                <td v-if="canKick" :class="[rh, 'w-[60px] border-l border-white/5 text-center']">
                    <button v-if="member.access_role !== 'leader'"
                            @click="emit('kick-member', member)"
                            class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-error/10 hover:bg-error text-error hover:text-white transition-all"
                            :title="__('Remove from static')">
                        <span class="material-symbols-outlined text-base leading-none">close</span>
                    </button>
                </td>
            </template>
            <template v-else>
                <td :class="[rh, 'w-[130px] border-l border-white/5']"></td>
                <td :class="[rh, 'w-[130px] border-l border-white/5']"></td>
                <td v-if="canKick" :class="[rh, 'w-[60px] border-l border-white/5']"></td>
            </template>
        </template>
    </tr>
</template>
