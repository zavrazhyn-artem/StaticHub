<template>
    <div class="space-y-3.5">
        <!-- Topbar: page title (left) + Sync badges (right) -->
        <div class="flex items-start justify-between gap-4 mb-4">
            <div>
                <div class="text-[11px] text-on-surface-variant uppercase tracking-[0.16em] font-bold">
                    {{ __('Command Dashboard') }}
                </div>
                <div class="flex items-center gap-2.5 mt-1">
                    <span class="w-2 h-2 rounded-full bg-success-neon shadow-[0_0_0_4px_rgba(57,255,20,0.18)]"></span>
                    <h1 class="text-[22px] font-extrabold tracking-tight text-on-surface">{{ data.staticName }}</h1>
                    <span class="text-[11px] text-on-surface-variant font-semibold ml-2">· {{ data.progressionLabel }}</span>
                </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <sync-badge
                    v-for="b in syncBadges"
                    :key="b.key"
                    :name="b.name"
                    :short-name="b.shortName"
                    :color="b.color"
                    :last-synced-at="b.lastSyncedAt"
                    :interval-min="b.intervalMin"
                    :now="now"
                />
            </div>
        </div>

        <!-- Row 1: Next Raid (1.7fr) + ME card (1fr) -->
        <div class="grid grid-cols-1 lg:grid-cols-[1.7fr_1fr] gap-3.5">
            <!-- Next Raid + Composition (placeholder for 2.2) -->
            <div class="rounded-2xl border border-primary/[0.22] p-5 relative overflow-hidden bg-gradient-to-br from-primary/[0.10] to-primary/[0.02]">
                <div class="absolute pointer-events-none" style="top: -50px; right: -50px; width: 240px; height: 240px; background: radial-gradient(circle, rgba(79,211,247,0.12), transparent 70%);"></div>
                <next-raid-card :next-raid="data.nextRaid" />
            </div>

            <!-- ME card -->
            <div class="rounded-2xl border border-white/[0.06] p-5 bg-surface-container">
                <me-card :me="data.me" />
            </div>
        </div>

        <!-- Row 2: Progression strip — wing labels + boss-pips from config('wow_season') -->
        <div class="rounded-2xl border border-white/[0.06] p-4 bg-surface-container">
            <div class="flex items-baseline justify-between mb-3">
                <div class="text-[11px] text-on-surface-variant uppercase tracking-[0.16em] font-bold">
                    {{ __('Raid Progression') }}
                </div>
                <div class="flex gap-3 text-[10px] font-mono text-on-surface-variant">
                    <span><b class="text-[#3a8dff]">{{ tally.N }}</b>/{{ tally.total }} N</span>
                    <span><b class="text-[#a855f7]">{{ tally.H }}</b>/{{ tally.total }} H</span>
                    <span><b class="text-[#fa7902]">{{ tally.M }}</b>/{{ tally.total }} M</span>
                </div>
            </div>
            <progression-strip :raid-progression="data.raidProgression" />
        </div>

        <!-- Row 3: Logistics (1.7fr) + AI report (1fr) — same grid as Row 1 -->
        <div class="grid grid-cols-1 lg:grid-cols-[1.7fr_1fr] gap-3.5">
            <div class="rounded-2xl border border-white/[0.06] p-4 bg-surface-container">
                <div class="flex items-baseline justify-between mb-3">
                    <div class="text-[11px] text-tertiary-dim uppercase tracking-[0.16em] font-bold">
                        {{ __('Logistics · Treasury') }}
                    </div>
                </div>
                <pantry-logistics-card
                    :reserves="data.reserves"
                    :autonomy="data.autonomy"
                    :recipes="data.recipes"
                    :raid-days="data.raidDays"
                    :paid-count="data.paidCount"
                    :sparkline="data.bankSparkline"
                    :routes="data.routes"
                />
            </div>

            <div class="rounded-2xl border border-white/[0.06] p-5 bg-surface-container">
                <ai-report-card :report="data.lastAiReport" />
            </div>
        </div>

    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useTranslation } from '@/composables/useTranslation'
import NextRaidCard from './NextRaidCard.vue'
import PantryLogisticsCard from './PantryLogisticsCard.vue'
import AiReportCard from './AiReportCard.vue'
import ProgressionStrip from './ProgressionStrip.vue'
import SyncBadge from './SyncBadge.vue'
import MeCard from './MeCard.vue'

const { __ } = useTranslation()

const props = defineProps({
    data: { type: Object, required: true },
})

const now = ref(Date.now())
let tick

onMounted(() => {
    tick = setInterval(() => { now.value = Date.now() }, props.data.tickInterval ?? 1000)
})
onBeforeUnmount(() => clearInterval(tick))

// Active services use the primary tint. Reserved: pass `unavailable: true`
// when a service is offline — SyncBadge will render the grey #9a9a9a state.
const tally = computed(() => props.data.progressionTally ?? { LFR: 0, N: 0, H: 0, M: 0, total: 0 })

const syncBadges = computed(() => {
    const sd = props.data.syncData ?? {}
    return [
        { key: 'bnet', name: 'Blizzard',      shortName: 'BLZ',    color: '#4fd3f7', lastSyncedAt: sd.bnet?.last_synced_at, intervalMin: sd.bnet?.interval_minutes ?? 60 },
        { key: 'rio',  name: 'Raider.io',     shortName: 'R.IO',   color: '#4fd3f7', lastSyncedAt: sd.rio?.last_synced_at,  intervalMin: sd.rio?.interval_minutes  ?? 60 },
        { key: 'wcl',  name: 'Warcraft Logs', shortName: 'W.LOGS', color: '#4fd3f7', lastSyncedAt: sd.wcl?.last_synced_at,  intervalMin: sd.wcl?.interval_minutes  ?? 60 },
    ]
})
</script>
