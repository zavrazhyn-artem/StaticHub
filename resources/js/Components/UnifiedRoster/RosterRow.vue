<script setup>
import SummaryCells from './Cells/SummaryCells.vue';
import RaidCells from './Cells/RaidCells.vue';
import GearCells from './Cells/GearCells.vue';

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
    roleIconSrc: { type: Function, required: true },
    tierCount: { type: Function, required: true },
    hasAuditIssues: { type: Function, required: true },
    auditTitle: { type: Function, required: true },
});

const emit = defineEmits([
    'toggle-expand',
    'update-access-role',
    'update-roster-status',
    'kick-member'
]);
</script>

<template>
    <tr :class="[
            isAlt ? 'bg-black/40 border-b border-white/5 text-[11px]' : 'border-b border-gray-800 hover:bg-gray-800/40 transition-all group/row',
            !isAlt && expanded ? 'bg-primary/5' : ''
        ]">

        <!-- Character identity -->
        <td :class="isAlt ? 'p-1.5 pl-[70px] h-[42px]' : 'p-2.5 h-[86px]'">
            <div :class="[
                'flex flex-col justify-center h-full gap-1',
                isAlt ? 'items-start' : 'items-center'
            ]">
                <div class="flex items-center gap-2">
                    <div class="relative shrink-0">
                        <img v-if="char?.avatar_url"
                             :src="char.avatar_url"
                             :class="isAlt ? 'w-5 h-5 rounded border border-white/10' : 'w-8 h-8 rounded-lg border border-white/10'"
                             :alt="char.name">
                        <div v-else :class="isAlt ? 'w-5 h-5 rounded bg-white/5 border border-white/10' : 'w-8 h-8 rounded-lg bg-white/5 border border-white/10 flex items-center justify-center'">
                            <span v-if="!isAlt" class="material-symbols-outlined text-gray-600 text-xs">person</span>
                        </div>
                        <div v-if="!isAlt && member.roster_status === 'bench'"
                             class="absolute -top-1 -right-1 bg-error-dim text-[7px] font-bold px-1 rounded uppercase border border-error">
                            {{ __('Bench') }}
                        </div>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-1">
                            <span class="font-bold tracking-tight truncate"
                                  :class="[classColors[char?.class] ?? 'text-white', isAlt ? 'text-[10px]' : 'text-xs']">
                                {{ char?.name || (isAlt ? __('Unknown') : member.name) }}
                            </span>
                            <img v-if="char?.combat_role"
                                 :src="roleIconSrc(char.combat_role)"
                                 :class="isAlt ? 'w-2.5 h-2.5 opacity-80' : 'w-3 h-3 opacity-80'"
                                 :title="char.combat_role">
                            <span v-if="isAlt" class="text-[8px] text-on-surface-variant font-bold uppercase">{{ char?.class ?? '' }}</span>

                            <button v-if="!isAlt && (member.alts || []).length > 0"
                                    @click="emit('toggle-expand', member.id)"
                                    class="text-on-surface-variant hover:text-white transition-colors">
                                <span class="material-symbols-outlined text-[10px]">
                                    {{ expanded ? 'expand_less' : 'expand_more' }}
                                </span>
                            </button>
                        </div>
                        <div v-if="!isAlt" class="text-[9px] text-on-surface-variant font-medium uppercase tracking-tighter truncate leading-tight">
                            {{ member.name }}
                        </div>
                    </div>
                </div>
            </div>
        </td>

        <!-- iLvL (hidden in raids and gear) -->
        <td v-if="activeTab !== 'raids' && activeTab !== 'gear'"
            :class="[isAlt ? 'p-1.5 h-[42px]' : 'p-2.5 h-[86px]', 'text-center font-mono font-bold text-cyan-400 border-l border-white/5']">
            {{ char?.equipped_ilvl != null
                ? Number(char.equipped_ilvl).toFixed(1)
                : 'N/A' }}
        </td>

        <!-- Summary Tab Cells -->
        <template v-if="activeTab === 'summary'">
            <SummaryCells :char="char"
                          :tier-colors="tierColors"
                          :tier-count="tierCount"
                          :is-alt="isAlt" />
        </template>

        <!-- Raid Tab Cells -->
        <template v-if="activeTab === 'raids'">
            <RaidCells :char="char"
                       :grouped-roster="groupedRoster"
                       :raid-columns="raidColumns"
                       :selected-difficulty="selectedDifficulty"
                       :kill-mark-class="killMarkClass"
                       :is-alt="isAlt" />
        </template>

        <!-- Gear Tab Cells -->
        <template v-if="activeTab === 'gear'">
            <GearCells :char="char"
                       :is-alt="isAlt"
                       :has-audit-issues="hasAuditIssues"
                       :audit-title="auditTitle" />

        </template>

        <!-- Audit badge (summary only) -->
        <template v-if="activeTab === 'summary'">
            <td :class="[isAlt ? 'p-1.5 h-[42px]' : 'p-2.5 h-[86px]', 'text-center border-l border-white/5']">
                <span v-if="hasAuditIssues(char)"
                      :class="[isAlt ? 'text-[8px] px-1' : 'text-[9px] px-1.5', 'inline-flex items-center gap-1 text-amber-400 bg-amber-400/10 border border-amber-400/20 rounded py-0.5 font-bold cursor-help']"
                      :title="auditTitle(char)">
                    <span class="material-symbols-outlined text-[10px]">warning</span>
                    {{ (char.missing_enchants_slots?.length ?? 0) + (char.empty_sockets_count ?? 0) }}
                </span>
                <span v-else class="text-gray-600 text-[10px]">✓</span>
            </td>

            <!-- Role / Status selects (summary only, main character only) -->
            <template v-if="!isAlt">
                <td class="p-2 w-[130px] h-[86px] border-l border-white/5 text-center">
                    <div v-if="canManageAccess">
                        <select :value="member.access_role"
                                @change="emit('update-access-role', member, $event.target.value)"
                                class="bg-black/40 border border-white/10 rounded text-[8px] font-bold uppercase text-white py-0 px-1 focus:border-primary transition-all w-full h-[24px]">
                            <option value="leader">{{ __('Leader') }}</option>
                            <option value="officer">{{ __('Officer') }}</option>
                            <option value="member">{{ __('Member') }}</option>
                        </select>
                    </div>
                    <div v-else class="text-center">
                        <span class="text-[9px] font-bold uppercase tracking-wider text-gray-300 bg-white/5 px-2 py-1 rounded border border-white/10">
                            {{ member.access_role }}
                        </span>
                    </div>
                </td>
                <td class="p-2 w-[130px] h-[86px] border-l border-white/5 text-center">
                    <div v-if="canManageStatus">
                        <select :value="member.roster_status"
                                @change="emit('update-roster-status', member, $event.target.value)"
                                class="bg-black/40 border border-white/10 rounded text-[8px] font-bold uppercase text-white py-0 px-1 focus:border-primary transition-all w-full h-[24px]">
                            <option value="core">{{ __('Core') }}</option>
                            <option value="bench">{{ __('Bench') }}</option>
                        </select>
                    </div>
                    <div v-else class="text-center">
                        <span class="text-[9px] font-bold uppercase tracking-wider text-gray-300 bg-white/5 px-2 py-1 rounded border border-white/10">
                            {{ member.roster_status }}
                        </span>
                    </div>
                </td>
            </template>
            <template v-else>
                <td class="w-[130px] h-[86px] border-l border-white/5"></td>
                <td class="w-[130px] h-[86px] border-l border-white/5"></td>
            </template>
        </template>
    </tr>
</template>
