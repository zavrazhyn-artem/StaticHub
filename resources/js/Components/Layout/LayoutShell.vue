<template>
    <app-sidebar
        v-if="sidebar"
        :is-open="sidebarOpen"
        :collapsed="collapsed"
        :tagline="sidebar.tagline"
        :dashboard-url="sidebar.dashboardUrl"
        :csrf="csrf"
        :static-info="sidebar.staticInfo"
        :primary-nav="sidebar.primaryNav"
        :account-nav="sidebar.accountNav"
        :user="sidebar.user"
        :footer="sidebar.footer"
        :lang="lang"
        @close="sidebarOpen = false"
        @toggle-collapsed="toggleCollapsed"
    />

    <top-right-controls
        :has-sidebar="!!sidebar"
        :sidebar-open="sidebarOpen"
        :show-lang="!sidebar"
        :lang="lang"
        :auth="auth"
        :ghost="ghost"
        :csrf="csrf"
        @toggle-sidebar="sidebarOpen = !sidebarOpen"
    />

    <rsvp-modal-host :csrf="csrf" />
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import AppSidebar from './AppSidebar.vue'
import TopRightControls from './TopRightControls.vue'
import RsvpModalHost from './RsvpModalHost.vue'
import { useNotifications } from '@/composables/useNotifications.js'

const props = defineProps({
    sidebar: { type: Object, default: null },
    lang:    { type: Object, required: true },
    auth:    { type: Object, default: null },
    ghost:   { type: Object, default: null },
    csrf:    { type: String, required: true },
    initialNotifications: { type: Array, default: () => [] },
})

const sidebarOpen = ref(false)
const collapsed = ref(false)
const { push } = useNotifications()

function readCollapsed() {
    try { return localStorage.getItem('sidebar_collapsed') === '1' } catch { return false }
}

function applyCollapsed(value) {
    const root = document.documentElement
    if (value) {
        root.style.setProperty('--sidebar-w', '72px')
        root.classList.add('sidebar-collapsed')
    } else {
        root.style.setProperty('--sidebar-w', '272px')
        root.classList.remove('sidebar-collapsed')
    }
}

function toggleCollapsed() {
    collapsed.value = !collapsed.value
}

watch(collapsed, (value) => {
    try { localStorage.setItem('sidebar_collapsed', value ? '1' : '0') } catch {}
    applyCollapsed(value)
})

onMounted(() => {
    collapsed.value = readCollapsed()
    applyCollapsed(collapsed.value)
    props.initialNotifications.forEach(n => push(n))
})
</script>
