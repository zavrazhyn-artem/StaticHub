<script setup>
import { useTranslation } from '@/composables/useTranslation';
const { __ } = useTranslation();

defineProps({
    scheduleUrl: { type: String, required: true },
    logsUrl:     { type: String, required: true },
    activeTab:   { type: String, required: true }, // 'schedule' | 'logs'
});
</script>

<template>
    <div class="flex items-center gap-2 border-b border-white/5 mb-8">
        <a :href="scheduleUrl"
           :class="['px-6 py-4 font-headline text-xs font-bold uppercase tracking-widest transition-all relative group',
                    activeTab === 'schedule' ? 'text-primary' : 'text-on-surface-variant hover:text-white']">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">calendar_month</span>
                {{ __('Schedule & Treasury') }}
            </div>
            <div v-if="activeTab === 'schedule'"
                 class="absolute bottom-0 left-0 w-full h-0.5 bg-primary shadow-[0_0_10px_rgba(34,211,238,0.5)]"></div>
        </a>
        <a :href="logsUrl"
           :class="['px-6 py-4 font-headline text-xs font-bold uppercase tracking-widest transition-all relative group',
                    activeTab === 'logs' ? 'text-primary' : 'text-on-surface-variant hover:text-white']">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">analytics</span>
                {{ __('Warcraft Logs & AI') }}
            </div>
            <div v-if="activeTab === 'logs'"
                 class="absolute bottom-0 left-0 w-full h-0.5 bg-primary shadow-[0_0_10px_rgba(34,211,238,0.5)]"></div>
        </a>
    </div>
</template>
