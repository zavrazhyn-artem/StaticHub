<script setup>
import { useWowClasses } from '../../composables/useWowClasses.js';

defineProps({
    character: { type: Object, required: true },
});

const { getClassColor, getSpecName } = useWowClasses();
</script>

<template>
    <div
        class="flex items-center justify-between px-2 py-1 rounded hover:bg-white/5 transition-colors group relative overflow-hidden"
        :class="character.pivot?.status === 'pending' ? 'opacity-40' : ''"
    >
        <div class="absolute left-0 top-0 bottom-0 w-0.5 opacity-60" :class="getClassColor(character.playable_class)"></div>

        <div class="flex items-center gap-2 pl-1.5">
            <div class="relative shrink-0">
                <img
                    :src="character.avatar_url"
                    class="w-9 h-9 rounded object-cover border border-white/10"
                    :class="character.pivot?.status === 'pending' ? 'grayscale' : ''"
                >

                <div
                    v-if="character.pivot?.status === 'present'"
                    class="absolute -top-1 -right-1 w-3 h-3 bg-success-neon rounded-full border border-surface-container flex items-center justify-center"
                    title="Present"
                >
                    <span class="material-symbols-outlined text-[9px] text-black font-bold">check</span>
                </div>

                <div
                    v-else-if="character.pivot?.status === 'late'"
                    class="absolute -top-1 -right-1 w-3 h-3 bg-yellow-400 rounded-full border border-surface-container flex items-center justify-center"
                    title="Late"
                >
                    <span class="material-symbols-outlined text-[9px] text-black font-bold">schedule</span>
                </div>

                <div
                    v-else-if="character.pivot?.status === 'tentative'"
                    class="absolute -top-1 -right-1 w-3 h-3 bg-yellow-500 rounded-full border border-surface-container flex items-center justify-center"
                    title="Tentative"
                >
                    <span class="material-symbols-outlined text-[9px] text-black font-bold">question_mark</span>
                </div>

                <div
                    v-else-if="character.pivot?.status === 'absent'"
                    class="absolute -top-1 -right-1 w-3 h-3 bg-error-dim rounded-full border border-surface-container flex items-center justify-center"
                    title="Absent"
                >
                    <span class="material-symbols-outlined text-[9px] text-white font-bold">close</span>
                </div>

                <div
                    v-else-if="character.pivot?.status === 'pending'"
                    class="absolute -top-1 -right-1 w-3 h-3 bg-white/40 rounded-full border border-surface-container flex items-center justify-center"
                    title="Pending"
                >
                    <span class="material-symbols-outlined text-[9px] text-white/70 font-bold">question_mark</span>
                </div>
            </div>

            <div class="flex flex-col justify-center">
                <div
                    class="text-[11px] font-bold leading-none"
                    :class="'text-wow-' + (character.playable_class || '').toLowerCase().replace(' ', '-')"
                >{{ character.name }}</div>

                <div class="text-[8px] text-on-surface-variant font-medium flex items-center gap-1 leading-none uppercase tracking-tighter mt-[2px]">
                    {{ getSpecName(character) }}
                    <span v-if="character.pivot?.status === 'pending'" class="opacity-50">(Pending)</span>
                </div>
            </div>
        </div>

        <div class="opacity-0 group-hover:opacity-100 transition-opacity">
            <span
                v-if="character.pivot?.comment"
                class="material-symbols-outlined text-on-surface-variant text-[12px] cursor-help"
                :title="character.pivot.comment"
            >chat_bubble</span>
        </div>
    </div>
</template>

<style scoped>
/* WoW class text colours — named with prefix to avoid Tailwind conflicts */
.text-wow-warrior      { color: #C69B6D; }
.text-wow-paladin      { color: #F48CBA; }
.text-wow-hunter       { color: #ABD473; }
.text-wow-rogue        { color: #FFF468; }
.text-wow-priest       { color: #FFFFFF; }
.text-wow-death-knight { color: #C41F3B; }
.text-wow-shaman       { color: #0070DD; }
.text-wow-mage         { color: #3FC7EB; }
.text-wow-warlock      { color: #8788EE; }
.text-wow-monk         { color: #00FF98; }
.text-wow-druid        { color: #FF7C0A; }
.text-wow-demon-hunter { color: #A330C9; }
.text-wow-evoker       { color: #33937F; }
</style>
