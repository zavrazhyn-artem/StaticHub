<script setup>
import { computed } from 'vue';

const props = defineProps({
  syncData: {
    type: Object,
    required: true
  }
});

const emit = defineEmits(['refresh']);

const services = [
  { id: 'bnet', name: 'Blizzard', icon: 'shield' },
  { id: 'rio', name: 'Raider.io', icon: 'trending_up' },
  { id: 'wcl', name: 'Warcraft Logs', icon: 'leaderboard' }
];

const getTimeAgo = (timestamp) => {
  if (!timestamp) return 'Never';
  const date = new Date(timestamp);
  const now = new Date();
  const diffInMinutes = Math.floor((now - date) / 60000);

  if (diffInMinutes < 1) return 'Just now';
  if (diffInMinutes < 60) return `${diffInMinutes}m ago`;
  const diffInHours = Math.floor(diffInMinutes / 60);
  if (diffInHours < 24) return `${diffInHours}h ago`;
  return date.toLocaleDateString();
};

const getNextRefresh = (timestamp) => {
  if (!timestamp) return 'Soon';
  const date = new Date(timestamp);
  const next = new Date(date.getTime() + 60 * 60000); // Assuming 1 hour cycle
  const now = new Date();
  const diffInMinutes = Math.floor((next - now) / 60000);

  if (diffInMinutes <= 0) return 'In queue';
  return `in ${diffInMinutes}m`;
};

const getProgress = (id) => {
  // Mock progress based on minutes since last sync
  const timestamp = props.syncData[id];
  if (!timestamp) return 0;
  const date = new Date(timestamp);
  const now = new Date();
  const diffInMinutes = Math.floor((now - date) / 60000);
  return Math.max(5, Math.min(100, 100 - (diffInMinutes / 60) * 100));
};

const handleRefresh = (serviceId) => {
  // Emit event to trigger manual sync
  emit('refresh', serviceId);
  console.log(`Manual sync triggered for: ${serviceId}`);
  // In a real app, this might call an API endpoint:
  // axios.post(`/statics/${props.syncData.static_id}/sync/${serviceId}`)
};
</script>

<template>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div v-for="service in services" :key="service.id"
         class="bg-[#111] border border-white/5 rounded-xl p-4 flex flex-col justify-between group hover:border-blue-500/30 transition-all duration-300">

      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-lg bg-blue-500/10 flex items-center justify-center border border-blue-500/20 group-hover:border-blue-500/50 transition-colors">
            <span class="material-symbols-outlined text-blue-500 text-xl">{{ service.icon }}</span>
          </div>
          <div>
            <h3 class="text-white font-headline text-xs font-bold uppercase tracking-widest">{{ service.name }}</h3>
            <p class="text-[10px] text-gray-500 font-medium uppercase tracking-tighter">Data Stream</p>
          </div>
        </div>
        <button @click="handleRefresh(service.id)"
                class="w-8 h-8 rounded-lg bg-white/5 border border-white/10 flex items-center justify-center text-gray-400 hover:text-blue-500 hover:border-blue-500/50 transition-all active:scale-95">
          <span class="material-symbols-outlined text-sm">sync</span>
        </button>
      </div>

      <div class="space-y-3">
        <div class="space-y-1">
          <div class="flex justify-between text-[9px] font-bold uppercase tracking-widest">
            <span class="text-gray-500">Sync Status</span>
            <span class="text-blue-500">{{ Math.round(getProgress(service.id)) }}%</span>
          </div>
          <div class="h-1 bg-white/5 rounded-full overflow-hidden">
            <div class="h-full bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.5)] transition-all duration-1000"
                 :style="{ width: getProgress(service.id) + '%' }"></div>
          </div>
        </div>

        <div class="flex justify-between items-end">
          <div>
            <div class="text-[8px] text-gray-600 font-bold uppercase tracking-[0.2em] mb-0.5">Last Refreshed</div>
            <div class="text-[10px] text-white font-bold uppercase tracking-wider">{{ getTimeAgo(syncData[service.id]) }}</div>
          </div>
          <div class="text-right">
            <div class="text-[8px] text-gray-600 font-bold uppercase tracking-[0.2em] mb-0.5">Next Run</div>
            <div class="text-[10px] text-blue-500/80 font-bold uppercase tracking-wider">{{ getNextRefresh(syncData[service.id]) }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
