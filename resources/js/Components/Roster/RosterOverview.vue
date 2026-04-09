<script setup>
import { ref, computed } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
const { __ } = useTranslation();
import TabSummary from './TabSummary.vue';
import TabGear from './TabGear.vue';
import TabVault from './TabVault.vue';
import TabRaids from './TabRaids.vue';

// const charactersList = ref(window.rosterData || []);
const charactersList = computed(() => window.rosterData || []);
console.log("RosterOverview: Initialized with", charactersList.value.length, "characters");
if (charactersList.value.length > 0) {
  console.log("First character:", charactersList.value[0]);
}

const tabs = [
  { id: 'summary', name: __('Summary'), component: TabSummary },
  { id: 'vault', name: __('Vault'), component: TabVault },
  { id: 'raids', name: __('Raids'), component: TabRaids },
  { id: 'gear', name: __('Gear'), component: TabGear },
];

const activeTab = ref('summary');

const activeComponent = computed(() => {
  return tabs.find(t => t.id === activeTab.value)?.component;
});
</script>

<template>
  <div class="space-y-6">
    <!-- Tab Navigation -->
    <div class="flex items-center gap-8 border-b border-white/10 px-2">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        @click="activeTab = tab.id"
        class="pb-4 text-sm font-medium transition-colors relative"
        :class="activeTab === tab.id ? 'text-cyan-400' : 'text-gray-400 hover:text-gray-200'"
      >
        {{ tab.name }}
        <!-- Active Indicator -->
        <span
          v-if="activeTab === tab.id"
          class="absolute bottom-0 left-0 right-0 h-0.5 bg-cyan-400 shadow-[0_0_8px_rgba(34,211,238,0.4)]"
        ></span>
      </button>
    </div>

    <!-- Tab Content -->
    <div v-if="charactersList.length > 0" class="transition-all duration-300">
      <component
        :is="activeComponent"
        :characters="charactersList"
      />
    </div>

    <!-- Empty State -->
    <div v-else class="flex flex-col items-center justify-center py-20 text-center bg-[#0e0e10] border border-white/5 rounded-lg">
      <div class="mb-8">
        <div class="text-6xl font-extrabold tracking-tight">
          <span class="text-white">Blast</span><span class="text-blue-500">R<span class="text-xs opacity-70 ml-1">r<span class="text-[10px] opacity-50 ml-0.5">r</span></span></span>
        </div>
      </div>
      <h3 class="text-lg font-medium text-white mb-2 italic uppercase tracking-widest">Blast Your Raid</h3>
      <p class="text-gray-400 max-w-sm">
        {{ __("We couldn't find any main characters in this raid group.") }}
        {{ __('Make sure characters are assigned as Main in the Tactical Roster.') }}
      </p>
    </div>
  </div>
</template>

<style scoped>
</style>
