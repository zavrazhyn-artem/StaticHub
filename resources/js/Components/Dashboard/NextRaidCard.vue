<template>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 relative">
        <!-- Left: countdown + date + actions -->
        <div>
            <div class="text-[11px] text-primary uppercase tracking-[0.18em] font-bold">{{ __('Next Raid') }}</div>

            <template v-if="nextRaid">
                <div
                    class="text-[32px] font-extrabold font-mono text-tertiary-dim mt-1.5 leading-none tracking-tight whitespace-nowrap"
                >{{ countdownDisplay }}</div>
                <div class="text-xs text-on-surface-variant mt-1.5">{{ nextRaid.date }} · {{ nextRaid.time }}</div>

                <div class="flex items-center gap-2 mt-3.5 flex-wrap">
                    <!-- Attended state: character avatar + class-colored name + status icon -->
                    <button
                        v-if="attended"
                        type="button"
                        @click="openRsvp"
                        class="h-10 flex items-center gap-2 pl-1 pr-3 rounded-lg bg-white/[0.04] border border-white/[0.10] hover:bg-white/[0.08] transition active:scale-95"
                        :title="__('Edit RSVP')"
                    >
                        <img
                            v-if="attended.character?.avatar_url"
                            :src="attended.character.avatar_url"
                            :alt="attended.character.name"
                            class="w-7 h-7 rounded-full object-cover"
                        >
                        <div class="flex flex-col items-start leading-tight">
                            <span
                                class="text-[12px] font-bold"
                                :style="{ color: attendedClassColor }"
                            >{{ attended.character?.name }}</span>
                            <span class="text-[9px] text-on-surface-variant uppercase tracking-wider">{{ statusLabel }}</span>
                        </div>
                        <img
                            :src="`/images/rsvp/rsvp_${attended.status}.svg`"
                            :alt="attended.status"
                            class="w-5 h-5 ml-1"
                        >
                    </button>

                    <!-- Not attended: "Sign up" CTA — same h-10 as Details button -->
                    <button
                        v-else
                        type="button"
                        @click="openRsvp"
                        class="h-10 px-4 rounded-lg text-[12px] font-extrabold tracking-wider uppercase text-black bg-tertiary-dim hover:brightness-110 active:scale-95 transition"
                    >{{ __('Sign up') }}</button>

                    <a
                        :href="nextRaid.href"
                        class="h-10 inline-flex items-center px-3.5 rounded-lg text-[12px] font-semibold text-on-surface-variant border border-white/[0.12] hover:bg-white/5 hover:text-on-surface transition"
                    >{{ __('Event details') }}</a>
                </div>

                <div v-if="nextRaid.discordPosted" class="flex items-center gap-1.5 text-success-neon mt-3 text-[10px] font-bold uppercase tracking-wider">
                    <span class="material-symbols-outlined text-sm">check_circle</span>
                    {{ __('Posted to Discord') }}
                </div>
            </template>

            <template v-else>
                <div class="text-2xl font-bold text-on-surface-variant italic mt-2">{{ __('No scheduled raids') }}</div>
            </template>
        </div>

        <!-- Right: composition (Tank / Heal / DPS) -->
        <div class="md:border-l md:border-white/[0.06] md:pl-6">
            <div class="flex items-baseline justify-between mb-2.5">
                <div class="text-[11px] text-on-surface-variant uppercase tracking-[0.18em] font-bold">{{ __('Composition') }}</div>
                <div class="text-[10px] text-on-surface-variant font-mono">{{ totalHave }} / {{ totalNeed }}</div>
            </div>

            <div class="space-y-2.5">
                <comp-row :label="__('Tanks')" :have="counts.tank" :need="2" color="#3a8dff"/>
                <comp-row :label="__('Healers')" :have="counts.heal" :need="5" color="#39FF14"/>
                <comp-row :label="__('DPS')" :have="counts.dps" :need="13" color="#ff5063"/>
            </div>

            <div class="flex items-center gap-3 mt-3 text-[11px] font-mono text-on-surface-variant">
                <span
                    v-for="b in statusBadges"
                    :key="b.key"
                    class="inline-flex items-center gap-1"
                    :title="b.title"
                >
                    <img :src="b.icon" :alt="b.title" class="w-4 h-4">
                    <span>{{ b.value }}</span>
                </span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useTranslation } from '@/composables/useTranslation'
import { useRsvpModal } from '@/composables/useRsvpModal.js'
import { useWowClasses } from '@/composables/useWowClasses'
import CompRow from './CompRow.vue'

const { __ } = useTranslation()
const { openModal } = useRsvpModal()
const { getClassTextColor } = useWowClasses()

const props = defineProps({
    nextRaid: { type: Object, default: null },
})

const attended = computed(() => props.nextRaid?.rsvpContext?.currentAttendance ?? null)
const attendedClassColor = computed(() => getClassTextColor(attended.value?.character?.playable_class))

const statusLabels = {
    present:   'PRESENT',
    absent:    'ABSENT',
    tentative: 'TENTATIVE',
    late:      'LATE',
    bench:     'BENCH',
    pending:   'PENDING',
}
const statusLabel = computed(() => __(statusLabels[attended.value?.status] ?? 'PENDING'))

function openRsvp() {
    if (!props.nextRaid?.rsvpContext) return
    openModal(props.nextRaid.rsvpContext)
}

// Composition is read from confirmed RSVPs (status=present|late) on this raid,
// not from the static roster.
const counts = computed(() => {
    const r = props.nextRaid?.confirmedRoles ?? {}
    return {
        tank: r.tank ?? 0,
        heal: r.heal ?? 0,
        dps:  (r.mdps ?? 0) + (r.rdps ?? 0),
    }
})

const statusCounts = computed(() => {
    const s = props.nextRaid?.statusCounts ?? {}
    return {
        go:    s.go    ?? 0,
        qm:    s.qm    ?? 0,
        no:    s.no    ?? 0,
        bench: s.bench ?? 0,
    }
})

const statusBadges = computed(() => [
    { key: 'go',    icon: '/images/rsvp/rsvp_present.svg',   title: __('Confirmed'),  value: statusCounts.value.go    },
    { key: 'qm',    icon: '/images/rsvp/rsvp_tentative.svg', title: __('Tentative'),  value: statusCounts.value.qm    },
    { key: 'no',    icon: '/images/rsvp/rsvp_absent.svg',    title: __('Absent'),     value: statusCounts.value.no    },
    { key: 'bench', icon: '/images/rsvp/rsvp_bench.svg',     title: __('Bench'),      value: statusCounts.value.bench },
])

const totalHave = computed(() => counts.value.tank + counts.value.heal + counts.value.dps)
const totalNeed = 20

// Countdown
const now = ref(Date.now())
let tick

onMounted(() => { tick = setInterval(() => { now.value = Date.now() }, 1000) })
onUnmounted(() => clearInterval(tick))

const countdownDisplay = computed(() => {
    if (!props.nextRaid?.timestamp) return '—'
    const diff = Math.max(0, props.nextRaid.timestamp * 1000 - now.value)
    const d = Math.floor(diff / 86400000)
    const h = Math.floor((diff % 86400000) / 3600000)
    const m = Math.floor((diff % 3600000) / 60000)
    const s = Math.floor((diff % 60000) / 1000)
    if (d > 0) return `${d}д ${String(h).padStart(2, '0')}г ${String(m).padStart(2, '0')}х`
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
})
</script>
