<template>
    <aside
        :class="[
            'fixed left-0 top-0 h-screen z-40 flex flex-col overflow-hidden border-r border-white/[0.06]',
            'transition-[width,transform] duration-200 ease-out lg:translate-x-0',
            isOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
            isCollapsed ? 'is-collapsed' : '',
        ]"
        :style="{
            width: effectivelyCollapsed ? '84px' : '272px',
            background: 'linear-gradient(180deg, #16161a 0%, #0d0d10 100%)',
        }"
        @mouseenter="hovered = true"
        @mouseleave="hovered = false"
    >
        <!-- Aura -->
        <div
            class="absolute pointer-events-none"
            style="top: -100px; left: -50px; width: 300px; height: 300px; background: radial-gradient(circle, rgba(79,211,247,0.08) 0%, transparent 70%);"
        ></div>

        <!-- Header: Logo + Wordmark + Lang + Collapse toggle.
             Fixed h-[68px] so the layout below doesn't shift on collapse. -->
        <div :class="['flex items-center h-[68px] shrink-0 relative', effectivelyCollapsed ? 'justify-center px-0 gap-0' : 'gap-3 px-4']">
            <a :href="dashboardUrl" class="shrink-0 grid place-items-center w-[34px] h-[34px]">
                <img
                    src="/images/logo.svg"
                    alt="BlastR Logo"
                    class="w-[34px] h-[34px] drop-shadow-[0_0_8px_rgba(58,223,250,0.5)]"
                />
            </a>

            <a
                v-if="!effectivelyCollapsed"
                :href="dashboardUrl"
                class="flex-1 min-w-0 whitespace-nowrap"
            >
                <div class="flex items-baseline" style="font-weight: 800; letter-spacing: 0.04em; font-size: 15px; color: #f9f5f8;">
                    <span>BLAST</span>
                    <span class="text-primary" style="text-shadow: 0 0 14px rgba(79,211,247,0.55); font-weight: 900;">R</span>
                </div>
                <div class="text-[9px] text-on-surface-variant uppercase tracking-[0.16em] font-bold mt-0.5">{{ tagline }}</div>
            </a>

            <div
                v-if="!effectivelyCollapsed"
                class="flex items-center gap-1 shrink-0"
            >
                <sidebar-lang-pill
                    :current="lang.current"
                    :locales="lang.locales"
                    :switch-url="lang.switchUrl"
                    :csrf="csrf"
                />

                <button
                    type="button"
                    @click="$emit('toggle-collapsed')"
                    :title="isCollapsed ? __('Expand sidebar') : __('Collapse sidebar')"
                    :aria-label="isCollapsed ? __('Expand sidebar') : __('Collapse sidebar')"
                    class="grid place-items-center w-7 h-7 rounded-lg text-on-surface-variant hover:text-on-surface hover:bg-white/5 transition active:scale-95"
                >
                    <span class="material-symbols-outlined text-[20px]">
                        {{ isCollapsed ? 'keyboard_double_arrow_right' : 'keyboard_double_arrow_left' }}
                    </span>
                </button>
            </div>
        </div>

        <!-- Static widget — fixed height, internal compact/full views fade. -->
        <div class="px-3.5 pb-3.5 relative">
            <sidebar-static-widget
                :static-name="staticInfo.name"
                :progression-label="staticInfo.progressionLabel"
                :next-raid="staticInfo.nextRaid"
                :can-invite="staticInfo.canInvite"
                :invite-url="staticInfo.inviteUrl"
                :csrf="csrf"
                :collapsed="effectivelyCollapsed"
            />
        </div>

        <!-- Nav glass cards — fixed padding so icons don't shift on collapse. -->
        <div class="flex-1 overflow-y-auto px-3 relative" style="scrollbar-width: none;">
            <div
                class="rounded-2xl p-2 border border-white/[0.06] backdrop-blur-md"
                style="background: rgba(255,255,255,0.025);"
            >
                <sidebar-nav-row
                    v-for="item in primaryNav"
                    :key="item.label"
                    :item="item"
                    :collapsed="effectivelyCollapsed"
                    class="mb-0.5 last:mb-0"
                />
            </div>

            <div
                class="mt-2.5 rounded-2xl p-2 border border-white/[0.06]"
                style="background: rgba(255,255,255,0.025);"
            >
                <sidebar-nav-row
                    v-for="item in accountNav"
                    :key="item.label"
                    :item="item"
                    :collapsed="effectivelyCollapsed"
                    class="mb-0.5 last:mb-0"
                />
            </div>
        </div>

        <!-- User card -->
        <sidebar-user-card
            :user="user"
            :role="user.role"
            :logout-url="user.logoutUrl"
            :csrf="csrf"
            :class-color="user.classColor"
            :collapsed="effectivelyCollapsed"
        />

        <!-- Footer tools — full grid in expanded, single Help icon in collapsed. -->
        <sidebar-footer-tools
            :feedback-url="footer.feedbackUrl"
            :discord-url="footer.discordUrl"
            :patreon-url="footer.patreonUrl"
            :help-url="footer.helpUrl"
            :collapsed="effectivelyCollapsed"
        />
    </aside>

    <!-- Mobile backdrop -->
    <div
        v-if="isOpen"
        @click="$emit('close')"
        class="fixed inset-0 bg-black/60 backdrop-blur-sm z-30 lg:hidden"
    ></div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useTranslation } from '@/composables/useTranslation'
import SidebarStaticWidget from './SidebarStaticWidget.vue'
import SidebarNavRow from './SidebarNavRow.vue'
import SidebarUserCard from './SidebarUserCard.vue'
import SidebarLangPill from './SidebarLangPill.vue'
import SidebarFooterTools from './SidebarFooterTools.vue'

const { __ } = useTranslation()

const props = defineProps({
    isOpen:        { type: Boolean, default: false },
    collapsed:     { type: Boolean, default: false },
    tagline:       { type: String,  default: 'RAID HUB' },
    dashboardUrl:  { type: String,  required: true },
    csrf:          { type: String,  required: true },
    staticInfo:    { type: Object,  required: true },
    primaryNav:    { type: Array,   required: true },
    accountNav:    { type: Array,   required: true },
    user:          { type: Object,  required: true },
    footer:        { type: Object,  required: true },
    lang:          { type: Object,  required: true },
})

defineEmits(['close', 'toggle-collapsed'])

const hovered = ref(false)

const isCollapsed = computed(() => props.collapsed)
// Hover expands the sidebar visually but doesn't change persisted state.
const effectivelyCollapsed = computed(() => props.collapsed && !hovered.value)
</script>

<style scoped>
.overflow-y-auto::-webkit-scrollbar { display: none; }
</style>
