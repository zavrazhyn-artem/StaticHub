<script setup>
import { defineProps, defineEmits } from 'vue';

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
  try {
    const date = new Date(timestamp);
    if (isNaN(date.getTime())) return 'Invalid Date';
    const now = new Date();
    const diffInMinutes = Math.floor((now - date) / 60000);

    if (diffInMinutes < 1) return 'Just now';
    if (diffInMinutes < 60) return `${diffInMinutes}m ago`;
    const diffInHours = Math.floor(diffInMinutes / 60);
    if (diffInHours < 24) return `${diffInHours}h ago`;
    return date.toLocaleDateString();
  } catch (e) {
    return 'Error';
  }
};

const getNextRefresh = (timestamp) => {
  if (!timestamp) return 'Soon';
  try {
    const date = new Date(timestamp);
    if (isNaN(date.getTime())) return 'Soon';
    const next = new Date(date.getTime() + 60 * 60000); // Assuming 1 hour cycle
    const now = new Date();
    const diffInMinutes = Math.floor((next - now) / 60000);

    if (diffInMinutes <= 0) return 'In queue';
    return `in ${diffInMinutes}m`;
  } catch (e) {
    return 'Soon';
  }
};

const getProgress = (id) => {
  if (!props.syncData || !id) return 0;
  const timestamp = props.syncData[id];
  if (!timestamp) return 0;
  try {
    const date = new Date(timestamp);
    if (isNaN(date.getTime())) return 0;
    const now = new Date();
    const diffInMinutes = Math.floor((now - date) / 60000);
    // Use Math.max to avoid negative progress if something is weird
    return Math.max(0, Math.min(100, 100 - (diffInMinutes / 60) * 100));
  } catch (e) {
    return 0;
  }
};

const handleRefresh = (serviceId) => {
  emit('refresh', serviceId);
};

const radius = 42;
const circumference = 2 * Math.PI * radius;
</script>

<template>
  <div class="grid grid-cols-3 gap-3 max-w-sm" v-if="services && services.length">
    <template v-for="service in services" :key="service?.id || Math.random()">
      <div v-if="service && service.id"
           @click="handleRefresh(service.id)"
           class="flex flex-col items-center group cursor-pointer active:scale-95 transition-all">

        <!-- Service Label -->
        <div class="text-[8px] font-black text-on-surface-variant uppercase tracking-widest mb-2 group-hover:text-primary transition-colors">
          {{ service.name }}
        </div>

        <div class="relative w-16 h-16 bg-black/20 border border-white/5 rounded-full flex items-center justify-center group-hover:border-primary/30 transition-all duration-300 overflow-hidden">
          <!-- Background Glow -->
          <div class="absolute inset-0 bg-primary/0 group-hover:bg-primary/5 transition-colors duration-500 pointer-events-none"></div>

          <div class="relative w-full h-full p-1.5">
            <!-- SVG Circular Progress -->
            <svg class="w-full h-full -rotate-90 transform" viewBox="0 0 100 100">
              <!-- Background Track -->
              <circle
                cx="50"
                cy="50"
                :r="radius"
                fill="transparent"
                stroke="currentColor"
                stroke-width="6"
                class="text-white/5"
              />
              <!-- Progress Bar -->
              <circle
                cx="50"
                cy="50"
                :r="radius"
                fill="transparent"
                stroke="currentColor"
                stroke-width="6"
                stroke-linecap="round"
                class="text-primary transition-all duration-1000 ease-out"
                :style="{
                  strokeDasharray: circumference,
                  strokeDashoffset: circumference - (getProgress(service.id) / 100) * circumference,
                  filter: 'drop-shadow(0 0 4px rgba(59, 130, 246, 0.5))'
                }"
              />
            </svg>

            <!-- Center Content -->
            <div class="absolute inset-0 flex flex-col items-center justify-center text-center px-1">
              <div class="text-[7px] text-gray-400 font-bold uppercase tracking-tighter leading-none mb-0.5">{{ getTimeAgo(syncData && service?.id ? syncData[service.id] : null) }}</div>
              <div class="text-[6px] text-primary/60 font-black uppercase tracking-tighter leading-none">{{ getNextRefresh(syncData && service?.id ? syncData[service.id] : null) }}</div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
