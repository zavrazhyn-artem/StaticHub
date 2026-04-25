<template>
    <div
        class="relative rounded-xl overflow-hidden bg-gradient-to-br from-primary/[0.10] to-primary/[0.02] border border-primary/[0.20]"
        style="height: 140px;"
    >
        <!-- Full view -->
        <div
            class="absolute inset-0 px-3.5 py-3 transition-opacity duration-150"
            :class="collapsed ? 'opacity-0 pointer-events-none' : 'opacity-100'"
        >
            <div class="flex items-center gap-2 mb-1.5 whitespace-nowrap">
                <span class="w-1.5 h-1.5 rounded-full bg-success-neon shadow-[0_0_0_3px_rgba(57,255,20,0.18)] shrink-0"></span>
                <span class="flex-1 text-[9px] text-on-surface-variant uppercase tracking-[0.14em] font-bold">{{ __('Static') }}</span>

                <button
                    v-if="canInvite"
                    type="button"
                    @click="onInvite"
                    :title="__('Generate invite link (officers only)')"
                    class="flex items-center gap-1.5 pl-1.5 pr-2 py-1 rounded-xl text-primary text-[9.5px] font-bold uppercase tracking-wider whitespace-nowrap transition active:scale-95 bg-gradient-to-br from-primary/20 to-primary/[0.08] border border-primary/40 hover:from-primary/30 hover:to-primary/[0.16]"
                >
                    <span class="material-symbols-outlined text-[13px]">person_add</span>
                    <span>{{ __('Invite') }}</span>
                </button>
            </div>

            <div class="text-sm font-extrabold text-on-surface leading-tight truncate" :title="staticName">{{ staticName }}</div>
            <div v-if="progressionLabel" class="text-[10px] text-primary mt-0.5 font-semibold truncate">{{ progressionLabel }}</div>

            <div v-if="nextRaid" class="flex items-end justify-between mt-2.5 pt-2.5 whitespace-nowrap gap-2">
                <div class="min-w-0 flex-1">
                    <div class="text-[8px] text-on-surface-variant uppercase tracking-[0.14em] font-bold">{{ __('Next Raid') }}</div>
                    <div class="text-sm font-extrabold text-tertiary-dim font-mono leading-none mt-0.5">{{ countdown }}</div>
                </div>

                <!-- Attended state: status icon + class-colored character name -->
                <button
                    v-if="attended"
                    type="button"
                    @click="openRsvp"
                    :title="`${attended.character?.name ?? ''} · ${attended.status}`"
                    class="shrink-0 flex items-center gap-1.5 pl-1 pr-2 py-1 rounded-2xl bg-white/[0.04] hover:bg-white/[0.10] transition active:scale-95"
                >
                    <img
                        :src="`/images/rsvp/rsvp_${attended.status}.svg`"
                        :alt="attended.status"
                        class="w-5 h-5"
                    >
                    <span
                        class="text-[10px] font-extrabold truncate max-w-[80px]"
                        :style="{ color: attendedClassColor }"
                    >{{ attended.character?.name }}</span>
                </button>

                <!-- Not attended: "Записатись" CTA -->
                <button
                    v-else
                    type="button"
                    @click="openRsvp"
                    class="shrink-0 px-3 py-1.5 rounded-2xl text-[9.5px] font-extrabold uppercase tracking-wider text-black bg-tertiary-dim hover:brightness-110 active:scale-95 transition"
                >{{ __('Sign up') }}</button>
            </div>
        </div>

        <!-- Compact view: mirrors the expanded layout slot-for-slot so
             dot, progression label and countdown stay at the same y-coords. -->
        <div
            class="absolute inset-0 px-3.5 py-3 transition-opacity duration-150 flex flex-col"
            :class="collapsed ? 'opacity-100' : 'opacity-0 pointer-events-none'"
        >
            <!-- Header row — same as expanded -->
            <div class="flex items-center gap-2 mb-1.5 whitespace-nowrap">
                <span class="mt-2.5 w-1.5 h-1.5 rounded-full bg-success-neon mx-auto shadow-[0_0_0_3px_rgba(57,255,20,0.18)] shrink-0"></span>
            </div>

            <!-- Static name slot (invisible placeholder to preserve y-rhythm) -->
            <div class="text-sm font-extrabold leading-tight invisible">·</div>

            <!-- Progression label — same offset & color as expanded -->
            <div
                v-if="progressionShort"
                class="text-[10px] text-primary my-2 font-semibold text-center"
            >{{ progressionShort }}</div>

            <!-- Countdown stays at the bottom -->
            <div class="mt-auto flex justify-center">
                <span
                    v-if="nextRaid"
                    class="text-sm font-extrabold text-tertiary-dim font-mono leading-none"
                >{{ countdownLargestUnit }}</span>
                <span v-else class="text-[8px] text-on-surface-variant uppercase tracking-wider font-bold">{{ __('LIVE') }}</span>
            </div>

        </div>

        <!-- Shared horizontal divider — outside both views, fixed y-coord.
             Width grows with the aside on hover-expand → "reveals" itself. -->
        <div class="absolute left-3.5 right-3.5 border-t border-white/[0.06]" style="top: 86px;"></div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useTranslation } from '@/composables/useTranslation'
import { useNotifications } from '@/composables/useNotifications.js'
import { useRsvpModal } from '@/composables/useRsvpModal.js'
import { useWowClasses } from '@/composables/useWowClasses'

const props = defineProps({
    staticName:        { type: String, required: true },
    progressionLabel:  { type: String, default: '' },
    nextRaid:          { type: Object, default: null },
    canInvite:         { type: Boolean, default: false },
    inviteUrl:         { type: String, default: '/invite' },
    csrf:              { type: String, required: true },
    collapsed:         { type: Boolean, default: false },
})

const { __ } = useTranslation()
const { push } = useNotifications()
const { openModal } = useRsvpModal()
const { getClassTextColor } = useWowClasses()

const attended = computed(() => props.nextRaid?.rsvpContext?.currentAttendance ?? null)
const attendedClassColor = computed(() => getClassTextColor(attended.value?.character?.playable_class))

function openRsvp() {
    if (!props.nextRaid?.rsvpContext) return
    openModal(props.nextRaid.rsvpContext)
}

const now = ref(Date.now())
let tick

onMounted(() => {
    tick = setInterval(() => { now.value = Date.now() }, 1000)
})
onBeforeUnmount(() => clearInterval(tick))

function diffMs() {
    if (!props.nextRaid?.timestamp) return 0
    return Math.max(0, props.nextRaid.timestamp * 1000 - now.value)
}

const countdown = computed(() => {
    if (!props.nextRaid?.timestamp) return '—'
    const diff = diffMs()
    const d = Math.floor(diff / 86400000)
    const h = Math.floor((diff % 86400000) / 3600000)
    const m = Math.floor((diff % 3600000) / 60000)
    const s = Math.floor((diff % 60000) / 1000)
    if (d > 0) return `${d}д ${String(h).padStart(2, '0')}г ${String(m).padStart(2, '0')}х`
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
})

const countdownShort = computed(() => {
    if (!props.nextRaid?.timestamp) return '—'
    const diff = diffMs()
    const d = Math.floor(diff / 86400000)
    const h = Math.floor((diff % 86400000) / 3600000)
    const m = Math.floor((diff % 3600000) / 60000)
    if (d > 0) return `${d}д ${h}г`
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
})

const countdownChars = computed(() => Array.from(countdownShort.value))
const countdownGroups = computed(() => countdownShort.value.split(/\s+/))

// Largest non-zero unit only (collapsed compact view): days → hours → mins → secs
const countdownLargestUnit = computed(() => {
    if (!props.nextRaid?.timestamp) return '—'
    const diff = diffMs()
    const d = Math.floor(diff / 86400000)
    if (d > 0) return `${d}д`
    const h = Math.floor((diff % 86400000) / 3600000)
    if (h > 0) return `${h}г`
    const m = Math.floor((diff % 3600000) / 60000)
    if (m > 0) return `${m}х`
    const s = Math.floor((diff % 60000) / 1000)
    return `${s}с`
})

// "Mythic 4/9" → "M 4/9" — keep just the difficulty letter for compact view.
const progressionShort = computed(() => {
    if (!props.progressionLabel) return ''
    const map = { Mythic: 'M', Heroic: 'H', Normal: 'N', LFR: 'LFR', 'Міфічна': 'M', 'Героїчна': 'H', 'Нормальна': 'N', 'Звичайно': 'N' }
    const parts = props.progressionLabel.split(/\s+/)
    if (parts.length < 2) return props.progressionLabel
    const letter = map[parts[0]] ?? parts[0][0]
    return `${parts.slice(1).join(' ')}`
})

async function onInvite() {
    try {
        const res = await fetch(props.inviteUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': props.csrf,
            },
        })
        const data = await res.json()
        if (data.link) {
            await navigator.clipboard.writeText(data.link)
            push({
                type: 'success',
                icon: 'check_circle',
                title: __('Invite link copied'),
                body: __('Share it with your invitee.'),
                autoDismissMs: 3000,
            })
        }
    } catch {
        push({
            type: 'error',
            icon: 'error',
            title: __('Invite failed'),
            body: __('Try again in a moment.'),
            autoDismissMs: 4000,
        })
    }
}
</script>
