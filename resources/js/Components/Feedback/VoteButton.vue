<script setup>
import { ref } from 'vue';
import { feedbackApi, routes } from './api.js';

const props = defineProps({
    postId: { type: Number, required: true },
    initialVoted: { type: Boolean, default: false },
    initialCount: { type: Number, default: 0 },
    isAuthenticated: { type: Boolean, default: false },
    variant: { type: String, default: 'vertical' }, // vertical (card) | compact (inline)
});

const emit = defineEmits(['signin-required', 'voted']);

const voted = ref(props.initialVoted);
const count = ref(props.initialCount);
const busy = ref(false);

async function toggle() {
    if (!props.isAuthenticated) {
        emit('signin-required');
        return;
    }
    if (busy.value) return;
    busy.value = true;

    const prevVoted = voted.value;
    const prevCount = count.value;
    voted.value = !prevVoted;
    count.value = prevVoted ? Math.max(0, prevCount - 1) : prevCount + 1;

    try {
        const { data } = await feedbackApi.post(routes.vote(props.postId));
        voted.value = data.voted;
        count.value = data.votes_count;
        emit('voted', { voted: voted.value, count: count.value });
    } catch (e) {
        voted.value = prevVoted;
        count.value = prevCount;
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <button
        v-if="variant === 'vertical'"
        type="button"
        @click.stop.prevent="toggle"
        :disabled="busy"
        class="flex flex-col items-center justify-center w-14 py-2 rounded-xl border transition-all select-none active:scale-95"
        :class="voted
            ? 'bg-primary/10 border-primary/40 text-primary'
            : 'bg-surface-container-high/60 border-white/5 text-on-surface-variant hover:border-primary/40 hover:text-primary'"
    >
        <span class="material-symbols-outlined text-base" :class="voted ? 'filled-icon' : ''">
            keyboard_arrow_up
        </span>
        <span class="text-sm font-semibold tabular-nums">{{ count }}</span>
    </button>

    <button
        v-else
        type="button"
        @click.stop.prevent="toggle"
        :disabled="busy"
        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full border text-xs font-semibold transition-all active:scale-95"
        :class="voted
            ? 'bg-primary/10 border-primary/40 text-primary'
            : 'bg-surface-container-high/60 border-white/5 text-on-surface-variant hover:border-primary/40 hover:text-primary'"
    >
        <span class="material-symbols-outlined text-sm leading-none">keyboard_arrow_up</span>
        {{ count }}
    </button>
</template>

<style scoped>
.filled-icon {
    font-variation-settings: 'FILL' 1;
}
</style>
