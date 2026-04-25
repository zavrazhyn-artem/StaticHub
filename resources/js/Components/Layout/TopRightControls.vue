<template>
    <div class="fixed top-4 right-4 z-50 flex flex-col items-end gap-2 pointer-events-none">
        <!-- Top row: hamburger (mobile) + ghost + lang + auth -->
        <div class="flex items-center gap-2 pointer-events-auto">
            <!-- Mobile hamburger — only when sidebar present -->
            <button
                v-if="hasSidebar"
                type="button"
                @click="$emit('toggle-sidebar')"
                class="lg:hidden flex items-center justify-center h-10 w-10 rounded-lg bg-surface-container-high border border-white/10 text-on-surface-variant hover:text-on-surface hover:bg-white/10 transition active:scale-95"
                :aria-label="__('Toggle menu')"
            >
                <span class="material-symbols-outlined">{{ sidebarOpen ? 'close' : 'menu' }}</span>
            </button>

            <!-- Ghost mode badge + exit -->
            <div
                v-if="ghost?.active"
                class="flex items-center gap-2 pl-3 pr-1 py-1 rounded-lg bg-fuchsia-500/10 border border-fuchsia-500/40"
            >
                <span class="material-symbols-outlined text-fuchsia-400 text-sm">visibility</span>
                <span class="text-[11px] font-bold uppercase tracking-wider text-fuchsia-300 hidden md:inline">
                    {{ __('Ghost') }}: {{ ghost.staticName }}
                </span>
                <form :action="ghost.exitUrl" method="POST" class="inline-flex">
                    <input type="hidden" name="_token" :value="csrf">
                    <button
                        type="submit"
                        class="flex items-center gap-1 px-2 py-1 rounded-md bg-fuchsia-500/20 hover:bg-fuchsia-500 hover:text-white text-fuchsia-300 text-[10px] font-bold uppercase tracking-wider transition active:scale-95"
                    >
                        <span class="material-symbols-outlined text-xs">arrow_back</span>
                        <span class="hidden md:inline">{{ __('Exit') }}</span>
                    </button>
                </form>
            </div>

            <!-- Lang (always for guests, also if not in sidebar) -->
            <sidebar-lang-pill
                v-if="showLang"
                :current="lang.current"
                :locales="lang.locales"
                :switch-url="lang.switchUrl"
                :csrf="csrf"
            />

            <!-- Sign in -->
            <a
                v-if="auth?.signInUrl"
                :href="auth.signInUrl"
                class="flex items-center gap-2 px-4 py-2 rounded-lg bg-cyan-500/10 border border-cyan-500/50 text-cyan-400 hover:bg-cyan-500 hover:text-white transition active:scale-95"
            >
                <span class="material-symbols-outlined text-sm">login</span>
                <span class="text-[10px] font-bold uppercase tracking-wider">{{ __('Sign in') }}</span>
            </a>
        </div>

        <!-- Notification stack -->
        <div class="pointer-events-auto">
            <notification-stack />
        </div>
    </div>
</template>

<script setup>
import { useTranslation } from '@/composables/useTranslation'
import SidebarLangPill from './SidebarLangPill.vue'
import NotificationStack from './NotificationStack.vue'

const { __ } = useTranslation()

defineProps({
    hasSidebar:   { type: Boolean, default: false },
    sidebarOpen:  { type: Boolean, default: false },
    showLang:     { type: Boolean, default: true  },
    lang:         { type: Object,  required: true },
    auth:         { type: Object,  default: null  },
    ghost:        { type: Object,  default: null  },
    csrf:         { type: String,  required: true },
})

defineEmits(['toggle-sidebar'])
</script>
