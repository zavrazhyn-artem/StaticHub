<template>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <div class="lg:col-span-2 space-y-6">

            <next-raid-card :next-raid="data.nextRaid" />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <roster-readiness-card :role-counts="data.roleCounts" />
                <treasury-card
                    :tax-status="data.taxStatus"
                    :tax-description="data.taxDescription"
                    :target-tax="data.targetTax"
                    :weekly-status="data.weeklyStatus"
                    :paid-count="data.paidCount"
                    :week-range="data.weekRange"
                    :routes="data.routes"
                />
            </div>

            <logistics-card
                :reserves="data.reserves"
                :autonomy="data.autonomy"
                :weekly-cost="data.weeklyCost"
                :recipes="data.recipes"
                :raid-days="data.raidDays"
                :routes="data.routes"
            />

        </div>

        <div class="flex flex-col gap-6 h-full">

            <div class="bg-surface-container-high rounded-2xl border border-white/5 p-6 items-center justify-center">
                <sync-status-widget
                    :sync-data="data.syncData"
                    :tick-interval="data.tickInterval"
                />
            </div>

            <upcoming-objectives-card
                :events="data.weeklySchedule"
                :event-base-url="data.routes.eventShow"
            />

        </div>
    </div>
</template>

<script setup>
import NextRaidCard from './NextRaidCard.vue'
import RosterReadinessCard from './RosterReadinessCard.vue'
import TreasuryCard from './TreasuryCard.vue'
import LogisticsCard from './LogisticsCard.vue'
import UpcomingObjectivesCard from './UpcomingObjectivesCard.vue'

defineProps({
    data: { type: Object, required: true },
})
</script>
