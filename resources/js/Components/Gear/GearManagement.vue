<script setup>
import { useTranslation } from '@/composables/useTranslation';
import EmptyState from '@/Components/UI/EmptyState.vue';

const { __ } = useTranslation();

defineProps({
    staticName: { type: String, required: true },
});
</script>

<template>
    <section class="mb-8">
        <header class="flex items-end justify-between border-b border-white/5 pb-4">
            <div>
                <span class="text-on-surface-variant font-headline text-xs font-bold uppercase tracking-widest">{{ staticName }}</span>
                <h1 class="font-headline text-4xl font-extrabold tracking-tight flex items-center gap-3">
                    <span class="material-symbols-outlined text-cyan-400">shield</span>
                    {{ __('Gear Management') }}
                </h1>
            </div>
        </header>
    </section>

    <div class="bg-surface-container-low border border-white/5 rounded-xl p-12 shadow-2xl backdrop-blur-sm">
        <EmptyState
            icon="construction"
            :title="__('Coming Soon')"
            :description="__('Gear tracking, BiS lists, and loot management tools are currently in development.')"
        />
    </div>
</template>
