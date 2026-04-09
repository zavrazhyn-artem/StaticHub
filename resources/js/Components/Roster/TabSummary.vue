<script setup>
import { ref } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
const { __ } = useTranslation();

defineProps({
  characters: Array
});

const expandedRows = ref(new Set());

const toggleRow = (id) => {
  if (expandedRows.value.has(id)) {
    expandedRows.value.delete(id);
  } else {
    expandedRows.value.add(id);
  }
};

const classColors = {
  'Death Knight': 'text-[#C41F3B]',
  'Demon Hunter': 'text-[#A330C9]',
  'Druid': 'text-[#FF7C0A]',
  'Evoker': 'text-[#33937F]',
  'Hunter': 'text-[#ABD473]',
  'Mage': 'text-[#3FC7EB]',
  'Monk': 'text-[#00FF98]',
  'Paladin': 'text-[#F48CBA]',
  'Priest': 'text-[#FFFFFF]',
  'Rogue': 'text-[#FFF468]',
  'Shaman': 'text-[#0070DD]',
  'Warlock': 'text-[#8788EE]',
  'Warrior': 'text-[#C69B6D]',
};

const getTierCount = (char) => {
  const pieces = char?.tier_pieces;
  if (!pieces) return 0;
  return Object.values(pieces).filter(v => v && v !== '-').length;
};

const hasTierSlot = (char, slot) => {
  const val = char?.tier_pieces?.[slot];
  return val && val !== '-';
};

const getVaultSlotCount = (runs, thresholds) => {
  if (!runs || !Array.isArray(runs)) return 0;
  let count = 0;
  for (const t of thresholds) {
    if (runs.length >= t) count++;
  }
  return count;
};

const getMplusVaultSlots = (char) => getVaultSlotCount(char?.vault_weekly_runs, [1, 4, 8]);

const getRaidVaultSlots = (char) => {
  const slots = char?.vault_raid_slots;
  if (!slots || !Array.isArray(slots)) return 0;
  return slots.filter(s => s !== null).length;
};

const getWorldVaultSlots = (char) => getVaultSlotCount(char?.vault_world_runs, [2, 4, 8]);

const getRaidProgression = (char) => {
  const raids = char?.raids;
  if (!raids) return '-';
  let totalKills = { M: 0, H: 0, N: 0, LFR: 0 };
  let totalBosses = 0;
  for (const bosses of Object.values(raids)) {
    totalBosses += bosses.length;
    for (const boss of bosses) {
      if (boss.M) totalKills.M++;
      if (boss.H) totalKills.H++;
      if (boss.N) totalKills.N++;
      if (boss.LFR) totalKills.LFR++;
    }
  }
  if (totalKills.M > 0) return `${totalKills.M}/${totalBosses} M`;
  if (totalKills.H > 0) return `${totalKills.H}/${totalBosses} H`;
  if (totalKills.N > 0) return `${totalKills.N}/${totalBosses} N`;
  if (totalKills.LFR > 0) return `${totalKills.LFR}/${totalBosses} LFR`;
  return '-';
};

</script>

<template>
  <div class="bg-[#0e0e10] border border-white/5 rounded-lg overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-left border-collapse min-w-max">
        <thead>
          <!-- Group Header -->
          <tr class="bg-black/20 text-gray-500 text-[9px] uppercase tracking-widest font-bold border-b border-white/5">
            <th class="p-2 pl-4 sticky left-0 z-20 bg-[#0e0e10] shadow-[2px_0_5px_rgba(0,0,0,0.3)]">{{ __('Character') }}</th>
            <th class="p-2 text-center border-l border-white/5">{{ __('iLvL') }}</th>
            <th colspan="6" class="p-2 text-center border-l border-white/5">{{ __('Tier Pieces') }}</th>
            <th class="p-2 text-center border-l border-white/5">{{ __('M+ Dungeons') }}</th>
            <th colspan="3" class="p-2 text-center border-l border-white/5">{{ __('Great Vault') }}</th>
            <th class="p-2 text-center border-l border-white/5">{{ __('M+ Rating') }}</th>
            <th colspan="3" class="p-2 text-center border-l border-white/5">{{ __('Audit') }}</th>
          </tr>
          <!-- Sub Header -->
          <tr class="bg-black/40 text-cyan-400 text-[10px] uppercase tracking-widest font-bold border-b border-white/5">
            <th class="p-4 sticky left-0 z-20 bg-[#0e0e10] min-w-[200px] shadow-[2px_0_5px_rgba(0,0,0,0.3)]">{{ __('Name') }}</th>
            <th class="p-4 text-center">{{ __('Avg') }}</th>

            <th class="p-4 text-center border-l border-white/5 w-10">#</th>
            <th class="p-2 text-center w-8">H</th>
            <th class="p-2 text-center w-8">S</th>
            <th class="p-2 text-center w-8">C</th>
            <th class="p-2 text-center w-8">G</th>
            <th class="p-2 text-center w-8">L</th>

            <th class="p-4 text-center border-l border-white/5">{{ __('This Week') }}</th>

            <th class="p-4 text-center border-l border-white/5 w-8">M+</th>
            <th class="p-4 text-center w-8">R</th>
            <th class="p-4 text-center w-8">W</th>

            <th class="p-4 text-center border-l border-white/5">{{ __('Rating') }}</th>

            <th class="p-4 text-center border-l border-white/5">{{ __('Enchants') }}</th>
            <th class="p-4 text-center">{{ __('Gems') }}</th>
            <th class="p-4 text-center">{{ __('Progress') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-white/5">
          <template v-for="(char, index) in characters" :key="char.id">
            <tr class="hover:bg-white/[0.02] transition-colors" :class="{ 'cursor-pointer': char.alts && char.alts.length > 0 }" @click="char.alts && char.alts.length > 0 && toggleRow(char.id)">
              <!-- Name & Index -->
              <td class="p-4 sticky left-0 z-10 bg-[#0e0e10]/95 backdrop-blur-sm border-r border-white/5 shadow-[2px_0_5px_rgba(0,0,0,0.3)]">
                <div class="flex items-center gap-3">
                  <span class="text-gray-600 font-mono text-xs w-4">{{ index + 1 }}</span>
                  <div class="w-4 flex items-center justify-center">
                    <svg v-if="char.alts && char.alts.length > 0"
                         xmlns="http://www.w3.org/2000/svg"
                         class="w-4 h-4 transition-transform duration-200"
                         :class="{ 'rotate-90': expandedRows.has(char.id) }"
                         viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                  </div>
                  <div class="w-8 h-8 rounded border border-white/10 bg-black/20 flex items-center justify-center overflow-hidden shrink-0">
                    <img v-if="char.avatar_url" :src="char.avatar_url" :alt="char.name" class="w-full h-full object-cover" />
                    <span v-else class="text-[10px] text-white/20">?</span>
                  </div>
                  <div class="min-w-0">
                    <div class="font-bold text-sm truncate" :class="classColors[char.class] || 'text-white'">
                      {{ char.name }}
                    </div>
                  </div>
                </div>
              </td>

              <!-- iLvL -->
              <td class="p-4 text-center font-mono font-bold text-cyan-400 border-l border-white/5">
                {{ char.equipped_ilvl ? Number(char.equipped_ilvl).toFixed(0) : '-' }}
              </td>

              <!-- Tier Pieces -->
              <td class="p-4 text-center font-bold text-white border-l border-white/5">
                {{ getTierCount(char) }}
              </td>
              <td v-for="slot in ['H', 'S', 'C', 'G', 'L']" :key="slot" class="p-2 text-center">
                <span v-if="hasTierSlot(char, slot)" class="text-green-500 font-bold">{{ slot }}</span>
                <span v-else class="text-gray-800">-</span>
              </td>

              <!-- Mythic Dungeons -->
              <td class="p-4 text-center font-mono font-bold text-white border-l border-white/5">
                {{ char.weekly_runs_count || 0 }}
              </td>

              <!-- Great Vault: M+ / Raid / World -->
              <td class="p-4 text-center font-mono font-bold border-l border-white/5" :class="getMplusVaultSlots(char) > 0 ? 'text-green-400' : 'text-gray-700'">
                {{ getMplusVaultSlots(char) }}
              </td>
              <td class="p-4 text-center font-mono font-bold" :class="getRaidVaultSlots(char) > 0 ? 'text-green-400' : 'text-gray-700'">
                {{ getRaidVaultSlots(char) }}
              </td>
              <td class="p-4 text-center font-mono font-bold" :class="getWorldVaultSlots(char) > 0 ? 'text-green-400' : 'text-gray-700'">
                {{ getWorldVaultSlots(char) }}
              </td>

              <!-- M+ Rating -->
              <td class="p-4 text-center font-mono font-bold border-l border-white/5" :class="char.mythic_rating > 0 ? 'text-purple-400' : 'text-gray-600'">
                {{ char.mythic_rating ? Math.round(Number(char.mythic_rating)) : '0' }}
              </td>

              <!-- Audit -->
              <td class="p-4 text-center font-mono text-sm border-l border-white/5">
                <span v-if="(char.missing_enchants_slots?.length || 0) + (char.low_quality_enchants_slots?.length || 0) > 0"
                      class="font-bold rounded px-2 py-0.5"
                      :class="(char.missing_enchants_slots?.length || 0) > 0 ? 'text-red-500 bg-red-500/10' : 'text-amber-400 bg-amber-400/10'">
                  {{ (char.missing_enchants_slots?.length || 0) + (char.low_quality_enchants_slots?.length || 0) }}
                </span>
                <span v-else class="text-gray-600">-</span>
              </td>
              <td class="p-4 text-center font-mono text-sm">
                <span v-if="(char.empty_sockets_count || 0) > 0" class="text-red-500 font-bold bg-red-500/10 rounded px-2 py-0.5">
                  {{ char.empty_sockets_count }}
                </span>
                <span v-else class="text-gray-600">-</span>
              </td>
              <td class="p-4 text-center">
                <span class="text-[10px] font-bold text-white whitespace-nowrap bg-white/5 px-2 py-1 rounded">
                  {{ getRaidProgression(char) }}
                </span>
              </td>
            </tr>

            <!-- Alt Rows -->
            <tr v-for="alt in char.alts" :key="'alt-'+alt.id" v-show="expandedRows.has(char.id)" class="bg-black/40 hover:bg-white/[0.02] transition-colors">
              <td class="p-4 sticky left-0 z-10 bg-[#0e0e10]/95 backdrop-blur-sm border-r border-white/5 shadow-[2px_0_5px_rgba(0,0,0,0.3)]">
                <div class="flex items-center gap-3 pl-8 relative">
                  <div class="absolute left-4 top-0 bottom-1/2 w-3 border-l-2 border-b-2 border-white/10 rounded-bl-sm"></div>
                  <div class="w-6 h-6 rounded border border-white/10 bg-black/20 flex items-center justify-center overflow-hidden shrink-0">
                    <img v-if="alt.avatar_url" :src="alt.avatar_url" :alt="alt.name" class="w-full h-full object-cover" />
                    <span v-else class="text-[10px] text-white/20">?</span>
                  </div>
                  <div class="min-w-0">
                    <div class="font-bold text-xs truncate" :class="classColors[alt.class] || 'text-white'">
                      {{ alt.name }}
                    </div>
                  </div>
                </div>
              </td>

              <td class="p-4 text-center font-mono text-xs text-cyan-400/70 border-l border-white/5">
                {{ alt.equipped_ilvl ? Number(alt.equipped_ilvl).toFixed(0) : '-' }}
              </td>

              <td class="p-4 text-center text-sm font-bold text-white/70 border-l border-white/5">{{ getTierCount(alt) }}</td>
              <td v-for="slot in ['H', 'S', 'C', 'G', 'L']" :key="'alt-tier-'+slot" class="p-2 text-center">
                <span v-if="hasTierSlot(alt, slot)" class="text-green-500/70 font-bold text-[10px]">{{ slot }}</span>
                <span v-else class="text-gray-800 text-[10px]">-</span>
              </td>

              <td class="p-4 text-center font-mono text-sm font-bold text-white/70 border-l border-white/5">{{ alt.weekly_runs_count || 0 }}</td>

              <td class="p-4 text-center font-mono text-sm font-bold border-l border-white/5" :class="getMplusVaultSlots(alt) > 0 ? 'text-green-400/70' : 'text-gray-800'">{{ getMplusVaultSlots(alt) }}</td>
              <td class="p-4 text-center font-mono text-sm font-bold" :class="getRaidVaultSlots(alt) > 0 ? 'text-green-400/70' : 'text-gray-800'">{{ getRaidVaultSlots(alt) }}</td>
              <td class="p-4 text-center font-mono text-sm font-bold" :class="getWorldVaultSlots(alt) > 0 ? 'text-green-400/70' : 'text-gray-800'">{{ getWorldVaultSlots(alt) }}</td>

              <td class="p-4 text-center font-mono text-sm font-bold border-l border-white/5" :class="alt.mythic_rating > 0 ? 'text-purple-400/70' : 'text-gray-800'">
                {{ alt.mythic_rating ? Math.round(Number(alt.mythic_rating)) : '0' }}
              </td>

              <td class="p-4 text-center font-mono text-[10px] border-l border-white/5">
                <span v-if="(alt.missing_enchants_slots?.length || 0) + (alt.low_quality_enchants_slots?.length || 0) > 0"
                      class="font-bold rounded px-2 py-0.5"
                      :class="(alt.missing_enchants_slots?.length || 0) > 0 ? 'text-red-500/70 bg-red-500/5' : 'text-amber-400/70 bg-amber-400/5'">
                  {{ (alt.missing_enchants_slots?.length || 0) + (alt.low_quality_enchants_slots?.length || 0) }}
                </span>
                <span v-else class="text-gray-800">-</span>
              </td>
              <td class="p-4 text-center font-mono text-[10px]">
                <span v-if="(alt.empty_sockets_count || 0) > 0" class="text-red-500/70 font-bold bg-red-500/5 rounded px-2 py-0.5">{{ alt.empty_sockets_count }}</span>
                <span v-else class="text-gray-800">-</span>
              </td>
              <td class="p-4 text-center">
                <span class="text-[9px] font-bold text-white/50 whitespace-nowrap bg-white/5 px-2 py-1 rounded">{{ getRaidProgression(alt) }}</span>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>
