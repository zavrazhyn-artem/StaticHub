<template>
    <div class="flex flex-col gap-2 w-[320px] pointer-events-none">
        <transition-group
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 translate-x-4"
            enter-to-class="opacity-100 translate-x-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0 translate-x-4"
        >
            <div
                v-for="n in state.items"
                :key="n.id"
                :class="[
                    'pointer-events-auto rounded-xl border backdrop-blur-md p-3 shadow-[0_8px_24px_rgba(0,0,0,0.4)]',
                    typeStyles[n.type] || typeStyles.info,
                ]"
            >
                <div class="flex items-start gap-3">
                    <span
                        v-if="n.icon"
                        class="material-symbols-outlined text-lg leading-none shrink-0"
                        :class="iconColor[n.type] || iconColor.info"
                    >{{ n.icon }}</span>

                    <div class="flex-1 min-w-0">
                        <div v-if="n.title" class="text-xs font-bold text-on-surface leading-tight">{{ n.title }}</div>
                        <div v-if="n.body" class="text-[11px] text-on-surface-variant mt-0.5 leading-snug">{{ n.body }}</div>

                        <div v-if="n.action" class="mt-2">
                            <button
                                v-if="typeof n.action.onClick === 'function'"
                                @click="runAction(n)"
                                type="button"
                                class="inline-flex items-center gap-1 px-3 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-white/10 hover:bg-white/20 text-on-surface transition active:scale-95"
                            >
                                {{ n.action.label }}
                                <span v-if="n.action.icon" class="material-symbols-outlined text-xs">{{ n.action.icon }}</span>
                            </button>
                            <a
                                v-else
                                :href="n.action.href"
                                :method="n.action.method"
                                class="inline-flex items-center gap-1 px-3 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-white/10 hover:bg-white/20 text-on-surface transition active:scale-95"
                            >
                                {{ n.action.label }}
                                <span v-if="n.action.icon" class="material-symbols-outlined text-xs">{{ n.action.icon }}</span>
                            </a>
                        </div>
                    </div>

                    <button
                        v-if="n.dismissible"
                        @click="dismiss(n.id)"
                        class="opacity-50 hover:opacity-100 transition shrink-0 -mr-1 -mt-1 p-1 rounded hover:bg-white/10"
                        aria-label="Dismiss"
                    >
                        <span class="material-symbols-outlined text-sm leading-none">close</span>
                    </button>
                </div>
            </div>
        </transition-group>
    </div>
</template>

<script setup>
import { useNotifications } from '@/composables/useNotifications.js'

const { state, dismiss } = useNotifications()

function runAction(n) {
    try {
        n.action.onClick()
    } catch (e) {
        console.error('Notification action failed:', e)
    }
    if (n.action.dismissAfter !== false) dismiss(n.id)
}

const typeStyles = {
    info:    'bg-cyan-500/10 border-cyan-500/30',
    success: 'bg-emerald-500/10 border-emerald-500/30',
    warning: 'bg-amber-500/10 border-amber-500/30',
    error:   'bg-rose-500/10 border-rose-500/30',
}

const iconColor = {
    info:    'text-cyan-400',
    success: 'text-emerald-400',
    warning: 'text-amber-400',
    error:   'text-rose-400',
}
</script>
