<template>
    <div class="px-3.5 pb-3.5 pt-1 relative flex items-center">
        <!-- Expanded: 4 buttons horizontally in a row -->
        <div v-if="!collapsed" class="grid grid-cols-4 gap-1.5 w-full">
            <a
                v-for="tool in tools"
                :key="tool.key"
                :href="tool.href"
                :target="tool.external ? '_blank' : null"
                :rel="tool.external ? 'noopener noreferrer' : null"
                :title="tool.label"
                :aria-label="tool.label"
                :style="{
                    backgroundColor: tool.color + '10',
                    borderColor: tool.color + '25',
                    color: tool.color,
                }"
                class="flex flex-col items-center justify-center gap-1 h-[60px] rounded-2xl border transition hover:brightness-125 whitespace-nowrap"
            >
                <span class="material-symbols-outlined text-lg leading-none">{{ tool.icon }}</span>
                <span class="text-[8.5px] font-bold uppercase tracking-wider">{{ tool.short }}</span>
            </a>
        </div>

        <!-- Collapsed: single 60×60 Help button, full menu-block width -->
        <a
            v-else
            :href="helpTool.href"
            :title="helpTool.label"
            :aria-label="helpTool.label"
            :style="{
                backgroundColor: helpTool.color + '10',
                borderColor: helpTool.color + '25',
                color: helpTool.color,
            }"
            class="flex flex-col items-center justify-center gap-1 w-[60px] h-[60px] mx-auto rounded-2xl border transition hover:brightness-125"
        >
            <span class="material-symbols-outlined text-lg leading-none">{{ helpTool.icon }}</span>
            <span class="text-[8.5px] font-bold uppercase tracking-wider">{{ helpTool.short }}</span>
        </a>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useTranslation } from '@/composables/useTranslation'

const props = defineProps({
    feedbackUrl: { type: String, required: true },
    discordUrl:  { type: String, default: 'https://discord.gg/rHcj6M5SEv' },
    patreonUrl:  { type: String, default: 'https://www.patreon.com/' },
    helpUrl:     { type: String, default: '#' },
    collapsed:   { type: Boolean, default: false },
})

const { __ } = useTranslation()

const tools = computed(() => [
    { key: 'discord',  icon: 'forum',              short: __('Discord'),  label: __('Join our Discord'), href: props.discordUrl,  color: '#5865F2', external: true  },
    { key: 'patreon',  icon: 'volunteer_activism', short: __('Patreon'),  label: __('Support on Patreon'), href: props.patreonUrl, color: '#F96854', external: true  },
    { key: 'feedback', icon: 'feedback',           short: __('Feedback'), label: __('Leave Feedback'), href: props.feedbackUrl, color: '#4fd3f7', external: false },
    { key: 'help',     icon: 'help',               short: __('Help'),     label: __('Help'),           href: props.helpUrl,     color: '#adaaad', external: false },
])

const helpTool = computed(() => tools.value.find(t => t.key === 'help'))
</script>
