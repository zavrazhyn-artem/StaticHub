<script setup>
defineProps({
    absentRoster: { type: Array, required: true },
});

const emit = defineEmits(['open-comment']);
</script>

<template>
    <section v-if="absentRoster.length > 0" class="space-y-4 pt-4">
        <h3 class="font-headline text-[10px] font-black text-on-surface-variant uppercase tracking-[0.2em] flex items-center gap-2 mb-2">
            <span class="material-symbols-outlined text-sm">person_off</span>
            {{ __('Absent / Tentative') }}
        </h3>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
            <div
                v-for="character in absentRoster"
                :key="character.id"
                class="bg-white/5 border border-white/5 p-2 rounded-xl flex items-center gap-3 opacity-60 hover:opacity-100 transition-opacity relative group backdrop-blur-sm"
            >
                <img
                    :src="character.avatar_url"
                    class="w-7 h-7 rounded-lg object-cover grayscale group-hover:grayscale-0 transition-all"
                >
                <div class="min-w-0 overflow-hidden">
                    <div class="text-[10px] font-bold text-white leading-tight truncate">{{ character.name }}</div>
                    <div
                        class="text-[8px] font-black uppercase tracking-widest truncate"
                        :class="['absent', 'pending'].includes(character.pivot.status)
                            ? 'text-error-dim'
                            : character.pivot.status === 'tentative'
                                ? 'text-yellow-400'
                                : 'text-primary'"
                    >{{ character.pivot.status }}</div>
                </div>
                <div v-if="character.pivot.comment" class="absolute top-1 right-1 opacity-100 transition-opacity">
                    <button
                        @click.stop="emit('open-comment', { characterName: character.name, comment: character.pivot.comment })"
                        class="material-symbols-outlined text-[14px] text-yellow-400/80 hover:text-yellow-400 animate-pulse"
                        :title="character.pivot.comment"
                    >chat_bubble</button>
                </div>
            </div>
        </div>
    </section>
</template>
