<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useTranslation } from '@/composables/useTranslation';

const { __ } = useTranslation();

const props = defineProps({
  syncData: {
    type: Object,
    required: true,
  },
  // How often the widget re-calculates progress/countdown (ms).
  // Driven by config('sync.widget_tick_ms') passed from the blade template.
  tickInterval: {
    type: Number,
    default: 1000,
  },
});

const services = [
  { id: 'bnet', name: 'Blizzard',      icon: 'shield'      },
  { id: 'rio',  name: 'Raider.io',     icon: 'trending_up' },
  { id: 'wcl',  name: 'Warcraft Logs', icon: 'leaderboard' },
];

// Reactive "current time" — updated on every tick so all computed values
// automatically re-evaluate without any manual wiring.
const now = ref(new Date());
let timer = null;

onMounted(() => {
  timer = setInterval(() => {
    now.value = new Date();
  }, props.tickInterval);
});

onUnmounted(() => {
  clearInterval(timer);
});

// -----------------------------------------------------------------------
// Helpers — all accept a service id and read from syncData[id]
// -----------------------------------------------------------------------

const getServiceData = (id) => props.syncData?.[id] ?? null;

const getLastSyncedAt = (id) => {
  const data = getServiceData(id);
  if (!data?.last_synced_at) return null;
  const d = new Date(data.last_synced_at);
  return isNaN(d.getTime()) ? null : d;
};

const getIntervalMs = (id) => {
  const data = getServiceData(id);
  const minutes = data?.interval_minutes ?? 60;
  return minutes * 60 * 1000;
};

const getTimeAgo = (id) => {
  const date = getLastSyncedAt(id);
  if (!date) return __('Never');

  const diffMs = now.value - date;
  const diffMinutes = Math.floor(diffMs / 60000);

  if (diffMinutes < 1)  return __('Just now');
  if (diffMinutes < 60) return `${diffMinutes}${__('minute_short')} ${__('ago')}`;
  const diffHours = Math.floor(diffMinutes / 60);
  if (diffHours < 24)   return `${diffHours}${__('hour_short')} ${__('ago')}`;
  return date.toLocaleDateString();
};

const getNextRefresh = (id) => {
  const date = getLastSyncedAt(id);
  if (!date) return __('Soon');

  const nextMs  = date.getTime() + getIntervalMs(id);
  const diffMs  = nextMs - now.value;

  if (diffMs <= 0) return __('In queue');

  const totalSeconds = Math.floor(diffMs / 1000);
  const minutes      = Math.floor(totalSeconds / 60);
  const seconds      = totalSeconds % 60;

  if (minutes > 0) return `⟳ ${minutes}${__('minute_short')} ${seconds}${__('second_short')}`;
  return `⟳ ${seconds}${__('second_short')}`;
};

// Progress = how much of the interval has NOT yet elapsed (100% = just synced).
const getProgress = (id) => {
  const date = getLastSyncedAt(id);
  if (!date) return 0;

  const elapsedMs  = now.value - date;
  const intervalMs = getIntervalMs(id);

  return Math.max(0, Math.min(100, 100 - (elapsedMs / intervalMs) * 100));
};

const radius       = 42;
const circumference = 2 * Math.PI * radius;
</script>

<template>
  <div class="grid grid-cols-3 gap-3 max-w-sm" v-if="services && services.length">
    <template v-for="service in services" :key="service?.id || Math.random()">
      <div v-if="service && service.id"
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
              <!-- Progress Arc — updates every tick -->
              <circle
                cx="50"
                cy="50"
                :r="radius"
                fill="transparent"
                stroke="currentColor"
                stroke-width="6"
                stroke-linecap="round"
                class="text-primary"
                :style="{
                  strokeDasharray: circumference,
                  strokeDashoffset: circumference - (getProgress(service.id) / 100) * circumference,
                  filter: 'drop-shadow(0 0 4px rgba(59, 130, 246, 0.5))',
                  transition: 'stroke-dashoffset 0.9s linear',
                }"
              />
            </svg>

            <!-- Center Content -->
            <div class="absolute inset-0 flex flex-col items-center justify-center text-center px-1">
              <div class="text-[7px] text-gray-400 font-bold uppercase tracking-tighter leading-none mb-0.5">
                {{ getTimeAgo(service.id) }}
              </div>
              <div class="text-[6px] text-primary/60 font-black uppercase tracking-tighter leading-none">
                {{ getNextRefresh(service.id) }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
