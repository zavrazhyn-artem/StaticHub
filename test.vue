<script setup>
</script>
<div class="flex justify-center items-center h-[120px] relative w-full px-2">
<div class="absolute top-1/2 -translate-y-1/2 left-2 right-2 h-[40px] bg-primary/10 border-y border-primary/20 rounded-md pointer-events-none"></div>

<div class="h-full w-1/2 relative group/hours">
    <button type="button" @click="stepScroll(endHoursRef, 'up')" class="absolute top-0 w-full z-[60] opacity-0 group-hover/hours:opacity-100 flex justify-center py-1 bg-surface-container-highest/80 backdrop-blur-sm transition-opacity">
        <span class="material-symbols-outlined text-primary text-base">expand_less</span>
    </button>

    <div ref="endHoursRef" @scroll="(e) => handleWheelScroll(e, 'hours', true)" class="h-full overflow-y-auto snap-y snap-mandatory hide-scrollbar">
        <div class="h-[40px]"></div>
        <div v-for="h in hours" :key="'sh'+h" class="h-[40px] flex items-center justify-center snap-center text-lg font-bold" :class="tempEndHour === h ? 'text-primary' : 'text-on-surface-variant/40'">{{ h }}</div>
        <div class="h-[40px]"></div>
    </div>

    <button type="button" @click="stepScroll(endHoursRef, 'down')" class="absolute bottom-0 w-full z-[60] opacity-0 group-hover/hours:opacity-100 flex justify-center py-1 bg-surface-container-highest/80 backdrop-blur-sm transition-opacity">
        <span class="material-symbols-outlined text-primary text-base">expand_more</span>
    </button>
</div>

<div class="text-xl font-bold text-on-surface-variant/50 pb-1">:</div>

<div class="h-full w-1/2 relative group/mins">
    <button type="button" @click="stepScroll(endMinutesRef, 'up')" class="absolute top-0 w-full z-[60] opacity-0 group-hover/mins:opacity-100 flex justify-center py-1 bg-surface-container-highest/80 backdrop-blur-sm transition-opacity">
        <span class="material-symbols-outlined text-primary text-base">expand_less</span>
    </button>

    <div ref="endMinutesRef" @scroll="(e) => handleWheelScroll(e, 'minutes', true)" class="h-full overflow-y-auto snap-y snap-mandatory hide-scrollbar">
        <div class="h-[40px]"></div>
        <div v-for="m in minutes" :key="'sm'+m" class="h-[40px] flex items-center justify-center snap-center text-lg font-bold" :class="tempEndMinute === m ? 'text-primary' : 'text-on-surface-variant/40'">{{ m }}</div>
        <div class="h-[40px]"></div>
    </div>

    <button type="button" @click="stepScroll(endMinutesRef, 'down')" class="absolute bottom-0 w-full z-[60] opacity-0 group-hover/mins:opacity-100 flex justify-center py-1 bg-surface-container-highest/80 backdrop-blur-sm transition-opacity">
        <span class="material-symbols-outlined text-primary text-base">expand_more</span>
    </button>
</div>
</div>
