<script setup>
import { ref, computed, onMounted, onUpdated } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
import { useGearSpecSwitch } from '@/composables/useGearSpecSwitch';
import GearCell from './GearCell.vue';
import GearSpecSwitcher from './GearSpecSwitcher.vue';

const { __ } = useTranslation();

/**
 * Full-width gear audit table for the Roster Gear tab.
 *
 * Each member in `members` matches the UnifiedRoster roster shape:
 *   { id, name, main_character: CompiledRosterMemberDTO, alts: CompiledRosterMemberDTO[] }
 *
 * The compiled DTO now includes an `equipment` array of flat item objects
 * produced by RosterCompilerService::resolveEquipment().
 */
const props = defineProps({
    members: { type: Array, required: true },
});

// ---------------------------------------------------------------------------
// Row expand / collapse (alts)
// ---------------------------------------------------------------------------

const expandedRows = ref(new Set());

const toggleRow = (id) => {
    expandedRows.value.has(id)
        ? expandedRows.value.delete(id)
        : expandedRows.value.add(id);
};

// ---------------------------------------------------------------------------
// Wowhead tooltip refresh
// ---------------------------------------------------------------------------

const refreshTooltips = () => {
    if (window.whTooltips?.refreshLinks) {
        window.whTooltips.refreshLinks();
    }
};

onMounted(refreshTooltips);
onUpdated(refreshTooltips);

// ---------------------------------------------------------------------------
// Per-character spec switching
// ---------------------------------------------------------------------------

const { getSpecData, selectSpec, getSpecOptions, getActiveSpec } = useGearSpecSwitch(refreshTooltips);

// ---------------------------------------------------------------------------
// Config
// ---------------------------------------------------------------------------

const GEAR_SLOTS = computed(() => [
    { key: 'HEAD',      label: __('Head')      },
    { key: 'NECK',      label: __('Neck')      },
    { key: 'SHOULDER',  label: __('Shoulders') },
    { key: 'BACK',      label: __('Cloak')     },
    { key: 'CHEST',     label: __('Chest')     },
    { key: 'WRIST',     label: __('Wrist')     },
    { key: 'HANDS',     label: __('Hands')     },
    { key: 'WAIST',     label: __('Waist')     },
    { key: 'LEGS',      label: __('Legs')      },
    { key: 'FEET',      label: __('Feet')      },
    { key: 'FINGER_1',  label: __('Ring')    },
    { key: 'FINGER_2',  label: __('Ring')    },
    { key: 'TRINKET_1', label: __('Trinket') },
    { key: 'TRINKET_2', label: __('Trinket') },
    { key: 'MAIN_HAND', label: __('Main Hand') },
    { key: 'OFF_HAND',  label: __('Off Hand')  },
]);

const CLASS_COLORS = {
    'Death Knight': 'text-[#C41F3B]', 'Demon Hunter': 'text-[#A330C9]',
    'Druid':        'text-[#FF7C0A]', 'Evoker':       'text-[#33937F]',
    'Hunter':       'text-[#ABD473]', 'Mage':         'text-[#3FC7EB]',
    'Monk':         'text-[#00FF98]', 'Paladin':      'text-[#F48CBA]',
    'Priest':       'text-[#FFFFFF]', 'Rogue':        'text-[#FFF468]',
    'Shaman':       'text-[#0070DD]', 'Warlock':      'text-[#8788EE]',
    'Warrior':      'text-[#C69B6D]',
};

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Converts the flat equipment array into a { SLOT_KEY: item } map for O(1)
 * lookup when rendering each GearCell.
 */
const toSlotMap = (equipment) => {
    if (!Array.isArray(equipment) || equipment.length === 0) return {};
    return Object.fromEntries(equipment.map(item => [item.slot, item]));
};

const getSetItemIds = (equipment) => {
    if (!Array.isArray(equipment)) return [];
    return equipment.filter(i => i.is_set_piece).map(i => i.id);
};

/** Members that have a linked character (filters out "no character" entries). */
const linkedMembers = (members) => members.filter(m => m.main_character != null);
</script>

<template>
    <div class="bg-surface-container-high rounded-2xl border border-white/5 overflow-hidden">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse min-w-max">

                <!-- ── thead ───────────────────────────────────────────────── -->
                <thead>
                    <!-- Group headers -->
                    <tr class="bg-black/20 text-gray-500 text-4xs uppercase tracking-widest font-bold border-b border-white/5">
                        <th class="p-2 pl-4 sticky left-0 z-20 bg-[#0e0e10] shadow-[2px_0_5px_rgba(0,0,0,0.3)] min-w-[200px]">
                            {{ __('Character') }}
                        </th>
                        <th colspan="2" class="p-2 text-center border-l border-white/5">{{ __('Audit') }}</th>
                        <th :colspan="GEAR_SLOTS.length" class="p-2 text-center border-l border-white/5">{{ __('Equipment') }}</th>
                    </tr>
                    <!-- Column sub-headers -->
                    <tr class="bg-black/40 text-emerald-400 text-3xs uppercase tracking-widest font-bold border-b border-white/5">
                        <th class="p-4 sticky left-0 z-20 bg-[#0e0e10] shadow-[2px_0_5px_rgba(0,0,0,0.3)]">
                            <div class="flex justify-between items-center pr-2">
                                <span>{{ __('Name') }}</span>
                                <span class="text-gray-500 font-mono text-4xs">{{ __('iLvL') }}</span>
                            </div>
                        </th>
                        <th class="p-2 text-center text-4xs">{{ __('Ench') }}</th>
                        <th class="p-2 text-center text-4xs">{{ __('Gems') }}</th>
                        <th v-for="slot in GEAR_SLOTS" :key="slot.key"
                            class="p-1 min-w-[42px] text-center text-4xs">
                            {{ slot.label }}
                        </th>
                    </tr>
                </thead>

                <!-- ── tbody ───────────────────────────────────────────────── -->
                <tbody class="divide-y divide-white/5">
                    <template v-for="member in linkedMembers(members)" :key="member.id">

                        <!-- Main character row -->
                        <tr class="hover:bg-white/[0.02] transition-colors"
                            :class="{ 'cursor-pointer': (member.alts || []).length > 0 }"
                            @click="(member.alts || []).length > 0 && toggleRow(member.id)">

                            <!-- Identity (sticky) -->
                            <td class="p-2 pl-4 sticky left-0 z-10 bg-[#0e0e10]/95 backdrop-blur-sm border-r border-white/5 shadow-[2px_0_5px_rgba(0,0,0,0.3)]">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-2">
                                        <!-- Expand chevron -->
                                        <div class="w-4 flex items-center justify-center shrink-0">
                                            <svg v-if="(member.alts || []).length > 0"
                                                 xmlns="http://www.w3.org/2000/svg"
                                                 class="w-4 h-4 transition-transform duration-200"
                                                 :class="{ 'rotate-90': expandedRows.has(member.id) }"
                                                 viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="9 18 15 12 9 6"></polyline>
                                            </svg>
                                        </div>
                                        <!-- Avatar -->
                                        <div class="w-8 h-8 rounded border border-white/10 bg-black/20 overflow-hidden shrink-0">
                                            <img v-if="member.main_character.avatar_url"
                                                 :src="member.main_character.avatar_url"
                                                 :alt="member.main_character.name ?? member.name"
                                                 class="w-full h-full object-cover" />
                                            <div v-else class="w-full h-full flex items-center justify-center">
                                                <span class="text-3xs text-white/20">?</span>
                                            </div>
                                        </div>
                                        <!-- Name / spec + switcher -->
                                        <div class="min-w-0">
                                            <div class="font-bold text-sm truncate"
                                                 :class="CLASS_COLORS[member.main_character.class] ?? 'text-white'">
                                                {{ member.main_character.name ?? member.name }}
                                            </div>
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-4xs text-gray-500 uppercase font-medium">
                                                    {{ getActiveSpec(member.main_character) ?? '—' }}
                                                </span>
                                                <GearSpecSwitcher
                                                    :availableSpecs="getSpecOptions(member.main_character)"
                                                    :activeSpec="getActiveSpec(member.main_character)"
                                                    :mainSpecName="member.main_character.main_spec?.name"
                                                    size="sm"
                                                    @select="selectSpec(member.main_character.id, $event)"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                    <!-- ilvl -->
                                    <div class="text-right shrink-0 pr-1">
                                        <span class="text-xs font-mono font-bold text-emerald-400">
                                            {{ getSpecData(member.main_character).equipped_ilvl != null
                                                ? Number(getSpecData(member.main_character).equipped_ilvl).toFixed(1)
                                                : 'N/A' }}
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <!-- Audit: enchants -->
                            <td class="p-2 text-center font-mono text-sm border-l border-white/5">
                                <span v-if="(getSpecData(member.main_character).missing_enchants_slots?.length ?? 0) + (getSpecData(member.main_character).low_quality_enchants_slots?.length ?? 0) > 0"
                                      class="font-bold"
                                      :class="(getSpecData(member.main_character).missing_enchants_slots?.length ?? 0) > 0 ? 'text-red-500' : 'text-amber-400'"
                                      :title="[
                                          (getSpecData(member.main_character).missing_enchants_slots?.length > 0 ? __('Missing:') + ' ' + getSpecData(member.main_character).missing_enchants_slots.join(', ') : ''),
                                          (getSpecData(member.main_character).low_quality_enchants_slots?.length > 0 ? __('Low quality:') + ' ' + getSpecData(member.main_character).low_quality_enchants_slots.join(', ') : ''),
                                      ].filter(Boolean).join(' | ')">
                                    {{ (getSpecData(member.main_character).missing_enchants_slots?.length ?? 0) + (getSpecData(member.main_character).low_quality_enchants_slots?.length ?? 0) }}
                                </span>
                                <span v-else class="text-gray-600 text-xs">✓</span>
                            </td>

                            <!-- Audit: empty sockets -->
                            <td class="p-2 text-center font-mono text-sm">
                                <span v-if="(getSpecData(member.main_character).empty_sockets_count ?? 0) > 0"
                                      class="text-red-500 font-bold">
                                    {{ getSpecData(member.main_character).empty_sockets_count }}
                                </span>
                                <span v-else class="text-gray-600 text-xs">✓</span>
                            </td>

                            <!-- Gear cells -->
                            <td v-for="slot in GEAR_SLOTS" :key="slot.key" class="p-1 text-center">
                                <div class="flex justify-center">
                                    <GearCell
                                        :item="toSlotMap(getSpecData(member.main_character).equipment)[slot.key] ?? null"
                                        :slotName="slot.key"
                                        :classId="member.main_character.class_id"
                                        :specId="member.main_character.spec_id"
                                        :setItemIds="getSetItemIds(getSpecData(member.main_character).equipment)" />
                                </div>
                            </td>
                        </tr>

                        <!-- Alt rows (visible when row is expanded) -->
                        <template v-if="expandedRows.has(member.id)">
                            <tr v-for="(alt, index) in (member.alts || [])"
                                :key="'alt-' + (alt?.id || index)"
                                class="bg-black/40 hover:bg-white/[0.02] transition-colors text-2xs">

                                <!-- Alt identity (sticky) -->
                                <td class="p-2 sticky left-0 z-10 bg-[#0e0e10]/95 backdrop-blur-sm border-r border-white/5 shadow-[2px_0_5px_rgba(0,0,0,0.3)]">
                                    <div class="flex items-center justify-between gap-3 pl-8 relative">
                                        <!-- L-shape connector -->
                                        <div class="absolute left-4 top-0 bottom-1/2 w-3 border-l-2 border-b-2 border-white/10 rounded-bl-sm"></div>
                                        <div class="flex items-center gap-2">
                                            <div class="w-6 h-6 rounded border border-white/10 bg-black/20 overflow-hidden shrink-0">
                                                <img v-if="alt?.avatar_url"
                                                     :src="alt.avatar_url"
                                                     :alt="alt.name"
                                                     class="w-full h-full object-cover" />
                                                <div v-else class="w-full h-full flex items-center justify-center">
                                                    <span class="text-3xs text-white/20">?</span>
                                                </div>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="font-bold text-xs truncate"
                                                     :class="CLASS_COLORS[alt?.class] ?? 'text-white'">
                                                    {{ alt?.name ?? 'Unknown' }}
                                                </div>
                                                <div class="flex items-center gap-1">
                                                    <span class="text-5xs text-gray-500 uppercase font-medium">
                                                        {{ getActiveSpec(alt) ?? '—' }}
                                                    </span>
                                                    <GearSpecSwitcher
                                                        :availableSpecs="getSpecOptions(alt)"
                                                        :activeSpec="getActiveSpec(alt)"
                                                        :mainSpecName="alt?.main_spec?.name"
                                                        size="xs"
                                                        @select="selectSpec(alt.id, $event)"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-right shrink-0 pr-1">
                                            <span class="text-3xs font-mono font-bold text-emerald-400/70">
                                                {{ getSpecData(alt)?.equipped_ilvl != null
                                                    ? Number(getSpecData(alt).equipped_ilvl).toFixed(1)
                                                    : 'N/A' }}
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Alt audit: enchants -->
                                <td class="p-2 text-center font-mono text-xs border-l border-white/5">
                                    <span v-if="(getSpecData(alt)?.missing_enchants_slots?.length ?? 0) + (getSpecData(alt)?.low_quality_enchants_slots?.length ?? 0) > 0"
                                          class="font-bold"
                                          :class="(getSpecData(alt)?.missing_enchants_slots?.length ?? 0) > 0 ? 'text-red-500/70' : 'text-amber-400/70'"
                                          :title="[
                                              (getSpecData(alt)?.missing_enchants_slots?.length > 0 ? __('Missing:') + ' ' + getSpecData(alt).missing_enchants_slots.join(', ') : ''),
                                              (getSpecData(alt)?.low_quality_enchants_slots?.length > 0 ? __('Low quality:') + ' ' + getSpecData(alt).low_quality_enchants_slots.join(', ') : ''),
                                          ].filter(Boolean).join(' | ')">
                                        {{ (getSpecData(alt)?.missing_enchants_slots?.length ?? 0) + (getSpecData(alt)?.low_quality_enchants_slots?.length ?? 0) }}
                                    </span>
                                    <span v-else class="text-gray-700 text-3xs">✓</span>
                                </td>

                                <!-- Alt audit: gems -->
                                <td class="p-2 text-center font-mono text-xs">
                                    <span v-if="(getSpecData(alt)?.empty_sockets_count ?? 0) > 0"
                                          class="text-red-500/70 font-bold">
                                        {{ getSpecData(alt).empty_sockets_count }}
                                    </span>
                                    <span v-else class="text-gray-700 text-3xs">✓</span>
                                </td>

                                <!-- Alt gear cells -->
                                <td v-for="slot in GEAR_SLOTS" :key="'alt-' + slot.key" class="p-1 text-center opacity-75">
                                    <div class="flex justify-center">
                                        <GearCell
                                            :item="toSlotMap(getSpecData(alt)?.equipment)?.[slot.key] ?? null"
                                            :slotName="slot.key"
                                            :classId="alt?.class_id"
                                            :specId="alt?.spec_id"
                                            :setItemIds="getSetItemIds(getSpecData(alt)?.equipment)" />
                                    </div>
                                </td>
                            </tr>
                        </template>

                    </template>
                </tbody>

            </table>
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar        { height: 6px; }
.custom-scrollbar::-webkit-scrollbar-track  { background: rgba(0, 0, 0, 0.2); }
.custom-scrollbar::-webkit-scrollbar-thumb  { background: rgba(255, 255, 255, 0.1); border-radius: 3px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.2); }
</style>
