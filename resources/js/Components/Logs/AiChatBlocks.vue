<script setup>
import { useTranslation } from '@/composables/useTranslation';
const { __ } = useTranslation();

const props = defineProps({
    blocks: { type: Array, default: () => [] },
});

const colorMap = {
    danger:  { text: 'text-red-400',    bg: 'bg-red-500/10',    border: 'border-red-500/30'    },
    warning: { text: 'text-amber-400',  bg: 'bg-amber-500/10',  border: 'border-amber-500/30'  },
    success: { text: 'text-green-400',  bg: 'bg-green-500/10',  border: 'border-green-500/30'  },
    info:    { text: 'text-primary',    bg: 'bg-primary/10',    border: 'border-primary/30'    },
};

function colorClasses(color) {
    return colorMap[color] ?? colorMap.info;
}
</script>

<template>
    <div class="space-y-2">
        <template v-for="(block, i) in blocks" :key="i">

            <!-- text -->
            <p v-if="block.type === 'text'"
               class="text-xs text-on-surface leading-relaxed">
                {{ block.content }}
            </p>

            <!-- list -->
            <ul v-else-if="block.type === 'list'"
                class="space-y-1 pl-1">
                <li v-for="(item, j) in block.items" :key="j"
                    class="flex items-start gap-2 text-xs text-on-surface leading-relaxed">
                    <span class="mt-1.5 w-1 h-1 rounded-full bg-primary flex-shrink-0"></span>
                    {{ item }}
                </li>
            </ul>

            <!-- metric -->
            <div v-else-if="block.type === 'metric'"
                 class="flex items-center justify-between px-3 py-1.5 rounded-lg bg-white/5">
                <span class="text-3xs font-semibold uppercase tracking-wider text-on-surface-variant">
                    {{ block.label }}
                </span>
                <span :class="['text-sm font-black', colorClasses(block.color).text]">
                    {{ block.value }}
                </span>
            </div>

            <!-- alert -->
            <div v-else-if="block.type === 'alert'"
                 :class="['flex items-start gap-2 px-3 py-2 rounded-lg border', colorClasses(block.level).bg, colorClasses(block.level).border]">
                <span :class="['material-symbols-outlined text-sm flex-shrink-0 mt-0.5', colorClasses(block.level).text]">
                    {{ block.level === 'danger' ? 'error' : block.level === 'success' ? 'check_circle' : block.level === 'warning' ? 'warning' : 'info' }}
                </span>
                <p :class="['text-xs leading-relaxed', colorClasses(block.level).text]">
                    {{ block.content }}
                </p>
            </div>

            <!-- directive -->
            <div v-else-if="block.type === 'directive'"
                 class="flex items-start gap-2 px-3 py-2 rounded-lg bg-primary/5 border border-primary/20">
                <span class="material-symbols-outlined text-sm text-primary flex-shrink-0 mt-0.5">arrow_forward</span>
                <p class="text-xs text-primary font-bold leading-relaxed">
                    {{ block.content }}
                </p>
            </div>

        </template>

        <!-- Fallback if blocks is empty -->
        <p v-if="!blocks || blocks.length === 0"
           class="text-xs text-on-surface-variant italic">
            {{ __('No response data.') }}
        </p>
    </div>
</template>
