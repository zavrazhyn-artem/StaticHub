<template>
    <!-- Fixed h-[80px] keeps user-card area identical between states. -->
    <div class="px-3.5 pt-3 pb-2 h-[80px] flex items-center">
        <!-- In collapsed mode the surface-container wrapper is dropped — avatar
             becomes a bare 36×36 rail item, matching Help/nav icon dimensions. -->
        <div
            v-if="!collapsed"
            class="w-full flex items-center rounded-xl bg-surface-container border border-white/[0.06] whitespace-nowrap px-3.5 py-3 gap-2.5"
        >
            <div class="relative shrink-0" :title="collapsed ? user.name : null">
                <img
                    v-if="user.avatarUrl"
                    :src="user.avatarUrl"
                    :alt="user.name"
                    class="w-9 h-9 rounded-full object-cover"
                    :style="{ borderColor: classColor + 'aa' }"
                    style="border-width: 1.5px;"
                >
                <div
                    v-else
                    class="w-9 h-9 rounded-full grid place-items-center font-bold text-sm"
                    :style="{
                        background: `linear-gradient(135deg, ${classColor}66, ${classColor}22)`,
                        border: `1.5px solid ${classColor}aa`,
                        color: classColor,
                    }"
                >{{ initial }}</div>

                <span
                    class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full bg-success-neon"
                    style="border: 2px solid #19191c;"
                ></span>
            </div>

            <div
                v-if="!collapsed"
                class="flex-1 min-w-0 text-left"
            >
                <div class="flex items-center gap-1">
                    <span
                        class="text-[13px] font-extrabold truncate"
                        :style="{ color: classColor }"
                    >{{ user.name }}</span>
                    <span
                        v-if="roleStar"
                        class="text-[11px] cursor-help text-tertiary-dim"
                        :title="roleLabel"
                    >★</span>
                </div>
                <div v-if="user.subtitle" class="text-[10px] text-on-surface-variant font-medium truncate">{{ user.subtitle }}</div>
            </div>

            <form
                v-if="!collapsed"
                :action="logoutUrl"
                method="POST"
                class="shrink-0"
            >
                <input type="hidden" name="_token" :value="csrf">
                <button
                    type="submit"
                    :title="__('Log Out')"
                    :aria-label="__('Log Out')"
                    class="grid place-items-center w-8 h-8 rounded-lg text-on-surface-variant hover:text-error hover:bg-white/5 transition active:scale-95"
                >
                    <span class="material-symbols-outlined text-lg">logout</span>
                </button>
            </form>
        </div>

        <!-- Collapsed: 60x60 surface-container card with avatar centered. -->
        <div
            v-else
            class="w-[60px] h-[60px] mx-auto grid place-items-center rounded-xl bg-surface-container border border-white/[0.06]"
            :title="user.name"
        >
            <div class="relative w-9 h-9">
                <img
                    v-if="user.avatarUrl"
                    :src="user.avatarUrl"
                    :alt="user.name"
                    class="w-9 h-9 rounded-full object-cover"
                    :style="{ borderColor: classColor + 'aa' }"
                    style="border-width: 1.5px;"
                >
                <div
                    v-else
                    class="w-9 h-9 rounded-full grid place-items-center font-bold text-sm"
                    :style="{
                        background: `linear-gradient(135deg, ${classColor}66, ${classColor}22)`,
                        border: `1.5px solid ${classColor}aa`,
                        color: classColor,
                    }"
                >{{ initial }}</div>
                <span
                    class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full bg-success-neon"
                    style="border: 2px solid #19191c;"
                ></span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useTranslation } from '@/composables/useTranslation'

const props = defineProps({
    user:       { type: Object, required: true },
    role:       { type: String, default: 'member' },
    logoutUrl:  { type: String, required: true },
    csrf:       { type: String, required: true },
    classColor: { type: String, default: '#FF7C0A' },
    collapsed:  { type: Boolean, default: false },
})

const { __ } = useTranslation()

const initial = computed(() => (props.user.name?.[0] ?? '?').toUpperCase())
const roleStar = computed(() => ['owner', 'officer'].includes(props.role))
const roleLabel = computed(() => props.role === 'owner' ? __('Owner — full control') : __('Officer'))
</script>
