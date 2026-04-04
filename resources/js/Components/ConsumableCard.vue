<script>
export default {
    name: 'ConsumableCard',
    props: {
        recipe: { type: Object, required: true },
        quantity: { type: Number, default: 0 },
    },
    emits: ['update:quantity'],
    data() {
        return { intervalId: null };
    },
    methods: {
        increment() {
            if (this.quantity < 9) this.$emit('update:quantity', this.quantity + 1);
        },
        decrement() {
            if (this.quantity > 0) this.$emit('update:quantity', this.quantity - 1);
        },
        startInterval(fn) {
            this.stopInterval();
            this.intervalId = setInterval(fn, 100);
        },
        stopInterval() {
            if (this.intervalId) {
                clearInterval(this.intervalId);
                this.intervalId = null;
            }
        },
    },
};
</script>

<template>
    <div class="bg-surface-container-highest p-2 rounded-lg relative group flex items-center justify-between gap-3">
        <div class="flex items-center gap-2 min-w-0">
            <div
                class="w-8 h-8 rounded p-1 flex items-center justify-center border overflow-hidden shrink-0"
                :class="`bg-gradient-to-br from-${recipe.color || 'primary'}/20 to-transparent border-${recipe.color || 'primary'}/30`"
            >
                <img v-if="recipe.icon_url" :src="recipe.icon_url" :alt="recipe.name" class="w-full h-full object-cover rounded-sm">
                <span v-else class="material-symbols-outlined" :class="`text-${recipe.color || 'primary'}`" style="font-variation-settings: 'FILL' 1;">{{ recipe.icon }}</span>
            </div>
            <div class="font-headline font-bold text-xs leading-tight truncate" :class="`text-${recipe.color || 'primary'}`">{{ recipe.name }}</div>
        </div>

        <div class="flex items-center gap-2 bg-black/20 rounded-md p-1 border border-white/5">
            <button
                type="button"
                @click="decrement"
                @mousedown="startInterval(decrement)"
                @mouseup="stopInterval"
                @mouseleave="stopInterval"
                :disabled="quantity <= 0"
                class="w-6 h-6 flex items-center justify-center rounded bg-surface-container-highest hover:bg-white/10 text-on-surface-variant transition-colors select-none disabled:opacity-30 disabled:cursor-not-allowed"
            >
                <span class="material-symbols-outlined text-sm">remove</span>
            </button>

            <div class="min-w-[1.5rem] text-center font-headline font-black text-white text-sm tabular-nums">{{ quantity }}</div>

            <button
                type="button"
                @click="increment"
                @mousedown="startInterval(increment)"
                @mouseup="stopInterval"
                @mouseleave="stopInterval"
                :disabled="quantity >= 9"
                class="w-6 h-6 flex items-center justify-center rounded bg-surface-container-highest hover:bg-white/10 text-on-surface-variant transition-colors select-none disabled:opacity-30 disabled:cursor-not-allowed"
            >
                <span class="material-symbols-outlined text-sm">add</span>
            </button>
        </div>
    </div>
</template>
