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

const parseRawData = (data) => {
    if (!data) return null;
    if (typeof data === 'object') return data;
    try {
        return JSON.parse(data);
    } catch (e) {
        return null;
    }
};

const formatActiveSpec = (spec) => {
    if (!spec) return 'Unknown';
    if (typeof spec === 'string' && spec.startsWith('{')) {
        try {
            const decoded = JSON.parse(spec);
            return decoded.name || spec;
        } catch (e) {
            return spec;
        }
    }
    return spec;
};

/**
 * getVaultSlot returns the value for a specific vault slot.
 * Categories: 'raid', 'mythic', 'world'
 * slotIndex: 0, 1, 2
 */
const getVaultSlot = (character, category, slotIndex) => {
    // Fallback if no specific weekly data is available yet
    // In a real scenario, we'd check mythic_plus_progression.current_period.slots

    // For now, let's try to extract from Raider.io weekly runs for M+
    const rioData = parseRawData(character?.raw_raiderio_data);

    if (category === 'mythic') {
        const weeklyRuns = rioData?.mythic_plus_weekly_highest_level_runs || [];
        // Sort runs by level descending
        const sortedRuns = weeklyRuns.length > 0 ? [...weeklyRuns].sort((a, b) => (b.mythic_level || 0) - (a.mythic_level || 0)) : [];

        // Vault M+ slots are unlocked at 1, 4, and 8 runs.
        // The level of the reward is based on the lowest of the top X runs.
        // Slot 1: Highest run
        // Slot 2: 4th highest run
        // Slot 3: 8th highest run

        if (slotIndex === 0 && sortedRuns.length >= 1) return `+${sortedRuns[0].mythic_level}`;
        if (slotIndex === 1 && sortedRuns.length >= 4) return `+${sortedRuns[3].mythic_level}`;
        if (slotIndex === 2 && sortedRuns.length >= 8) return `+${sortedRuns[7].mythic_level}`;

        return 0;
    }

    if (category === 'raid') {
        // Estimate from raid progression summary "X/Y H"
        // In reality, this should come from specific boss kills this week.
        const raids = rioData?.raid_progression || {};
        const keys = Object.keys(raids);
        if (keys.length === 0) return 0;
        const latestRaidKey = keys.pop();
        const progress = raids[latestRaidKey];

        if (progress?.summary) {
            const parts = progress.summary.split('/');
            if (parts.length < 2) return 0;
            const killed = parseInt(parts[0]);
            const difficultyParts = progress.summary.split(' ');
            const difficulty = difficultyParts.length > 0 ? difficultyParts.pop() : ''; // e.g., "H", "M", "N"

            // Vault Raid slots usually at 2, 4, 6 bosses
            if (slotIndex === 0 && killed >= 2) return difficulty;
            if (slotIndex === 1 && killed >= 4) return difficulty;
            if (slotIndex === 2 && killed >= 6) return difficulty;
        }
        return 0;
    }

    return 0;
};

const getSlotStyle = (value) => {
    if (!value || value === 0) return 'text-gray-700';

    // M+ values like "+10"
    if (typeof value === 'string' && value.startsWith('+')) {
        const level = parseInt(value.substring(1));
        if (level >= 10) return 'text-purple-400 font-bold';
        if (level >= 7) return 'text-blue-400 font-bold';
        return 'text-green-400 font-bold';
    }

    // Raid values like "M", "H", "N"
    if (value === 'M') return 'text-purple-400 font-bold';
    if (value === 'H') return 'text-blue-400 font-bold';
    if (value === 'N') return 'text-green-400 font-bold';

    return 'text-white font-bold';
};

</script>

<template>
  <div class="bg-[#0e0e10] border border-white/5 rounded-lg overflow-hidden">
    <div class="overflow-x-auto custom-scrollbar">
      <table class="w-full text-left border-collapse min-w-max">
        <thead>
          <!-- Grouped Categories -->
          <tr class="bg-black/20 text-gray-500 text-[9px] uppercase tracking-widest font-bold border-b border-white/5">
            <th class="p-2 pl-4 sticky left-0 z-20 bg-[#0e0e10] shadow-[2px_0_5px_rgba(0,0,0,0.3)]">{{ __('Character') }}</th>
            <th colspan="3" class="p-2 text-center border-l border-white/5">{{ __('Raids') }}</th>
            <th colspan="3" class="p-2 text-center border-l border-white/10">{{ __('M+ Dungeons') }}</th>
            <th colspan="3" class="p-2 text-center border-l border-white/10">{{ __('Delves / World') }}</th>
          </tr>
          <!-- Slot Headers -->
          <tr class="bg-black/40 text-cyan-400 text-[10px] uppercase tracking-widest font-bold border-b border-white/5">
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
                    <span v-else class="text-[10px] text-white/20">?</span>
                  </div>
                  <div class="min-w-0">
                    <div class="font-bold text-sm truncate" :class="classColors[char.playable_class] || 'text-white'">
                      {{ char.name }}
                    </div>
                    <div class="text-[9px] text-gray-500 uppercase font-medium truncate">
                      {{ formatActiveSpec(char.active_spec) }} {{ char.playable_class }}
                    </div>
                  </div>
                </div>
              </td>

              <!-- Raid Slots -->
              <td v-for="i in [0, 1, 2]" :key="'raid-'+i" class="p-4 text-center font-mono text-sm border-l border-white/5">
                <span :class="getSlotStyle(getVaultSlot(char, 'raid', i))">
                  {{ getVaultSlot(char, 'raid', i) || '-' }}
                </span>
              </td>

              <!-- M+ Slots -->
              <td v-for="i in [0, 1, 2]" :key="'mythic-'+i" class="p-4 text-center font-mono text-sm border-l border-white/10">
                <span :class="getSlotStyle(getVaultSlot(char, 'mythic', i))">
                  {{ getVaultSlot(char, 'mythic', i) || '-' }}
                </span>
              </td>

              <!-- World Slots -->
              <td v-for="i in [0, 1, 2]" :key="'world-'+i" class="p-4 text-center font-mono text-sm border-l border-white/10">
                <span :class="getSlotStyle(getVaultSlot(char, 'world', i))">
                  {{ getVaultSlot(char, 'world', i) || '-' }}
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
                    <span v-else class="text-[10px] text-white/20">?</span>
                  </div>
                  <div class="min-w-0">
                    <div class="font-bold text-xs truncate" :class="classColors[alt.playable_class] || 'text-white'">
                      {{ alt.name }}
                    </div>
                    <div class="text-[8px] text-gray-600 uppercase font-medium truncate">
                      {{ formatActiveSpec(alt.active_spec) }} {{ alt.playable_class }}
                    </div>
                  </div>
                </div>
              </td>

              <!-- Raid Slots (Alt) -->
              <td v-for="i in [0, 1, 2]" :key="'alt-raid-'+i" class="p-4 text-center font-mono text-xs border-l border-white/5 opacity-80">
                <span :class="getSlotStyle(getVaultSlot(alt, 'raid', i))">
                  {{ getVaultSlot(alt, 'raid', i) || '-' }}
                </span>
              </td>

              <!-- M+ Slots (Alt) -->
              <td v-for="i in [0, 1, 2]" :key="'alt-mythic-'+i" class="p-4 text-center font-mono text-xs border-l border-white/10 opacity-80">
                <span :class="getSlotStyle(getVaultSlot(alt, 'mythic', i))">
                  {{ getVaultSlot(alt, 'mythic', i) || '-' }}
                </span>
              </td>

              <!-- World Slots (Alt) -->
              <td v-for="i in [0, 1, 2]" :key="'alt-world-'+i" class="p-4 text-center font-mono text-xs border-l border-white/10 opacity-80">
                <span :class="getSlotStyle(getVaultSlot(alt, 'world', i))">
                  {{ getVaultSlot(alt, 'world', i) || '-' }}
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
