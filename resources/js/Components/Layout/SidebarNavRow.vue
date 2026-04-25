<template>
    <a
        :href="item.href"
        :title="collapsed ? item.label : null"
        :class="[
            'flex items-center py-2.5 rounded-xl text-[13px] font-semibold transition-all duration-200 border',
            'whitespace-nowrap min-w-0',
            collapsed ? 'justify-center px-0 gap-0' : 'justify-start px-3.5 gap-3.5',
            item.active
                ? 'bg-primary/10 text-primary border-primary/30'
                : 'text-on-surface border-transparent hover:bg-white/[0.035]',
        ]"
    >
        <span
            class="material-symbols-outlined text-[21px] leading-none relative shrink-0 mx-2"
            :class="item.active ? 'opacity-100' : 'opacity-75'"
        >
            {{ item.icon }}

            <!-- Mini badge anchored on the icon — visible only when collapsed -->
            <span
                v-if="item.badge"
                class="absolute -top-0.5 -right-1 w-2 h-2 rounded-full transition-opacity duration-150"
                :class="[
                    collapsed ? 'opacity-100' : 'opacity-0',
                    item.badge.type === 'alert' ? 'animate-pulse-dot' : '',
                ]"
                :style="{
                    background: item.badge.color,
                    boxShadow: `0 0 6px ${item.badge.color}aa`,
                }"
            ></span>
        </span>

        <!-- Label + full badge — fade out when collapsed; positions don't move -->
        <span
            class="flex-1 truncate transition-opacity duration-150"
            :class="collapsed ? 'opacity-0' : 'opacity-100'"
        >{{ item.label }}</span>

        <span
            v-if="item.badge?.type === 'alert'"
            class="inline-block w-2 h-2 rounded-full animate-pulse-dot transition-opacity duration-150 shrink-0"
            :class="collapsed ? 'opacity-0' : 'opacity-100'"
            :style="{ background: item.badge.color, boxShadow: `0 0 8px ${item.badge.color}aa` }"
        ></span>

        <span
            v-else-if="item.badge?.type === 'next'"
            class="text-[9.5px] font-bold tracking-wide font-mono transition-opacity duration-150 shrink-0"
            :class="collapsed ? 'opacity-0' : 'opacity-100'"
            :style="{ color: item.badge.color }"
        >{{ item.badge.value }}</span>

        <span
            v-else-if="item.badge?.type === 'count'"
            class="text-[10.5px] font-bold px-2 py-0.5 rounded-[10px] transition-opacity duration-150 shrink-0"
            :class="collapsed ? 'opacity-0' : 'opacity-100'"
            :style="{
                background: item.badge.color + '22',
                color: item.badge.color,
            }"
        >{{ item.badge.value }}</span>
    </a>
</template>

<script setup>
defineProps({
    item:      { type: Object, required: true },
    collapsed: { type: Boolean, default: false },
})
</script>
