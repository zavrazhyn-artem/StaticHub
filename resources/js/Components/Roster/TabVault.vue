<script setup>
import { ref } from 'vue';

const props = defineProps({
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

const getSpecName = (char) => {
    if (!char) return '';
    const ms = char.main_spec;
    if (ms && typeof ms === 'object') return ms.name || '';
    if (typeof ms === 'string') return ms;
    return '';
};

/**
 * getVaultSlot returns {ilvl, track} for a specific vault slot, or null if not unlocked.
 *
 * Sources (from compiled_data):
 *   mythic → vault_weekly_runs  [{mythic_level, ilvl, track}] sorted desc; slots at 1/4/8 runs
 *   raid   → vault_raid_slots   [slot1, slot2, slot3] precomputed by compiler; slots at 2/4/6 kills
 *   world  → vault_world_runs   [{tier, ilvl, track}] sorted desc; slots at 2/4/8 delves
 */
const getVaultSlot = (character, category, slotIndex) => {
    if (category === 'mythic') {
        const runs = character?.vault_weekly_runs || [];
        const needed = [1, 4, 8][slotIndex];
        if (runs.length >= needed) {
            const run = runs[needed - 1];
            return { ilvl: run.ilvl, track: run.track };
        }
        return null;
    }

    if (category === 'raid') {
        // Use precomputed vault_raid_slots from compiler (weekly boss kills)
        const slots = character?.vault_raid_slots;
        if (slots && slots[slotIndex]) {
            return { ilvl: slots[slotIndex].ilvl, track: slots[slotIndex].track };
        }
        return null;
    }

    if (category === 'world') {
        const runs = character?.vault_world_runs || [];
        // World vault slots: index 1 (2nd), index 3 (4th), index 7 (8th)
        const needed = [2, 4, 8][slotIndex];
        if (runs.length >= needed) {
            const run = runs[needed - 1];
            return { ilvl: run.ilvl, track: run.track };
        }
        return null;
    }

    return null;
};

const trackColor = {
    Myth:      'text-orange-400 font-bold',
    Hero:      'text-purple-400 font-bold',
    Champion:  'text-blue-400 font-bold',
    Veteran:   'text-green-400 font-bold',
    Adventurer:'text-teal-400 font-bold',
};

const getSlotStyle = (slot) => {
    if (!slot) return 'text-gray-700';
    return trackColor[slot.track] || 'text-white font-bold';
};

</script>

<template>
  <div class="bg-[#0e0e10] border border-white/5 rounded-lg overflow-hidden">
    <div class="overflow-x-auto custom-scrollbar">
      <table class="w-full text-left border-collapse min-w-max">
        <thead>
          <!-- Grouped Categories -->
          <tr class="bg-black/20 text-gray-500 text-4xs uppercase tracking-wider font-semibold border-b border-white/5">
            <th class="p-2 pl-4 sticky left-0 z-20 bg-[#0e0e10] shadow-[2px_0_5px_rgba(0,0,0,0.3)]">{{ __('Character') }}</th>
            <th colspan="3" class="p-2 text-center border-l border-white/5">{{ __('Raids') }}</th>
            <th colspan="3" class="p-2 text-center border-l border-white/10">{{ __('M+ Dungeons') }}</th>
            <th colspan="3" class="p-2 text-center border-l border-white/10">{{ __('Delves / World') }}</th>
          </tr>
          <!-- Slot Headers -->
          <tr class="bg-black/40 text-emerald-400 text-3xs uppercase tracking-wider font-semibold border-b border-white/5">
            <th class="p-4 sticky left-0 z-20 bg-[#0e0e10] min-w-[200px] shadow-[2px_0_5px_rgba(0,0,0,0.3)]">{{ __('Name') }}</th>

            <!-- Raid Slots -->
            <th class="p-4 text-center border-l border-white/5 w-20">{{ __('Slot 1') }}</th>
            <th class="p-4 text-center w-20">{{ __('Slot 2') }}</th>
            <th class="p-4 text-center w-20">{{ __('Slot 3') }}</th>

            <!-- M+ Slots -->
            <th class="p-4 text-center border-l border-white/10 w-20">{{ __('Slot 1') }}</th>
            <th class="p-4 text-center w-20">{{ __('Slot 2') }}</th>
            <th class="p-4 text-center w-20">{{ __('Slot 3') }}</th>

            <!-- World Slots -->
            <th class="p-4 text-center border-l border-white/10 w-20">{{ __('Slot 1') }}</th>
            <th class="p-4 text-center w-20">{{ __('Slot 2') }}</th>
            <th class="p-4 text-center w-20">{{ __('Slot 3') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-white/5">
          <template v-for="char in characters" :key="char.id">
            <tr class="hover:bg-white/[0.02] transition-colors group" @click="char.alts && char.alts.length > 0 && toggleRow(char.id)" :class="{ 'cursor-pointer': char.alts && char.alts.length > 0 }">
              <!-- Name -->
              <td class="p-4 sticky left-0 z-10 bg-[#0e0e10]/95 backdrop-blur-sm border-r border-white/5 shadow-[2px_0_5px_rgba(0,0,0,0.3)]">
                <div class="flex items-center gap-3">
                  <!-- Expand Icon -->
                  <div class="w-4 flex items-center justify-center">
                    <svg v-if="char.alts && char.alts.length > 0"
                         xmlns="http://www.w3.org/2000/svg"
                         class="w-4 h-4 transition-transform duration-200"
                         :class="{ 'rotate-90': expandedRows.has(char.id) }"
                         viewBox="0 0 24 24"
                         fill="none"
                         stroke="currentColor"
                         stroke-width="2"
                         stroke-linecap="round"
                         stroke-linejoin="round"
                    >
                      <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                    <div v-else class="w-4"></div>
                  </div>

                  <div class="w-8 h-8 rounded border border-white/10 bg-black/20 flex items-center justify-center overflow-hidden shrink-0">
                    <img v-if="char.avatar_url" :src="char.avatar_url" :alt="char.name" class="w-full h-full object-cover" />
                    <span v-else class="text-3xs text-white/20">?</span>
                  </div>
                  <div class="min-w-0">
                    <div class="font-bold text-sm truncate" :class="classColors[char.class] || 'text-white'">
                      {{ char.name }}
                    </div>
                    <div class="text-4xs text-gray-500 uppercase font-medium truncate">
                      {{ getSpecName(char) }} {{ char.class }}
                    </div>
                  </div>
                </div>
              </td>

              <!-- Raid Slots -->
              <td v-for="i in [0, 1, 2]" :key="'raid-'+i" class="p-4 text-center font-mono text-sm border-l border-white/5">
                <span :class="getSlotStyle(getVaultSlot(char, 'raid', i))">
                  {{ getVaultSlot(char, 'raid', i)?.ilvl || '-' }}
                </span>
              </td>

              <!-- M+ Slots -->
              <td v-for="i in [0, 1, 2]" :key="'mythic-'+i" class="p-4 text-center font-mono text-sm border-l border-white/10">
                <span :class="getSlotStyle(getVaultSlot(char, 'mythic', i))">
                  {{ getVaultSlot(char, 'mythic', i)?.ilvl || '-' }}
                </span>
              </td>

              <!-- World Slots -->
              <td v-for="i in [0, 1, 2]" :key="'world-'+i" class="p-4 text-center font-mono text-sm border-l border-white/10">
                <span :class="getSlotStyle(getVaultSlot(char, 'world', i))">
                  {{ getVaultSlot(char, 'world', i)?.ilvl || '-' }}
                </span>
              </td>
            </tr>

            <!-- Alts Rows -->
            <tr v-for="alt in char.alts" :key="'alt-'+alt.id" v-show="expandedRows.has(char.id)" class="bg-black/40 border-b border-white/5 transition-colors text-[0.9em]">
              <!-- Name with connector -->
              <td class="p-4 sticky left-0 z-10 bg-[#0e0e10]/95 backdrop-blur-sm border-r border-white/5 shadow-[2px_0_5px_rgba(0,0,0,0.3)]">
                <div class="flex items-center gap-3 pl-4">
                  <!-- Visual connector -->
                  <div class="w-4 h-5 border-l-2 border-b-2 border-white/10 -mt-3 rounded-bl"></div>

                  <div class="w-7 h-7 rounded border border-white/10 bg-black/20 flex items-center justify-center overflow-hidden shrink-0">
                    <img v-if="alt.avatar_url" :src="alt.avatar_url" :alt="alt.name" class="w-full h-full object-cover opacity-70" />
                    <span v-else class="text-3xs text-white/20">?</span>
                  </div>
                  <div class="min-w-0">
                    <div class="font-bold text-xs truncate" :class="classColors[alt.class] || 'text-white'">
                      {{ alt.name }}
                    </div>
                    <div class="text-5xs text-gray-600 uppercase font-medium truncate">
                      {{ getSpecName(alt) }} {{ alt.class }}
                    </div>
                  </div>
                </div>
              </td>

              <!-- Raid Slots (Alt) -->
              <td v-for="i in [0, 1, 2]" :key="'alt-raid-'+i" class="p-4 text-center font-mono text-xs border-l border-white/5 opacity-80">
                <span :class="getSlotStyle(getVaultSlot(alt, 'raid', i))">
                  {{ getVaultSlot(alt, 'raid', i)?.ilvl || '-' }}
                </span>
              </td>

              <!-- M+ Slots (Alt) -->
              <td v-for="i in [0, 1, 2]" :key="'alt-mythic-'+i" class="p-4 text-center font-mono text-xs border-l border-white/10 opacity-80">
                <span :class="getSlotStyle(getVaultSlot(alt, 'mythic', i))">
                  {{ getVaultSlot(alt, 'mythic', i)?.ilvl || '-' }}
                </span>
              </td>

              <!-- World Slots (Alt) -->
              <td v-for="i in [0, 1, 2]" :key="'alt-world-'+i" class="p-4 text-center font-mono text-xs border-l border-white/10 opacity-80">
                <span :class="getSlotStyle(getVaultSlot(alt, 'world', i))">
                  {{ getVaultSlot(alt, 'world', i)?.ilvl || '-' }}
                </span>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  height: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: rgba(0, 0, 0, 0.2);
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 3px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.2);
}
</style>
