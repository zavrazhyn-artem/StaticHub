<template>
    <div class="space-y-6">

        <static-achievements-card :raid-progression="data.raidProgression" />

        <roster-readiness-bar :role-counts="data.roleCounts" />

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <div class="lg:col-span-2 space-y-6">

                <next-raid-card :next-raid="data.nextRaid" />

                <logistics-card
                    :reserves="data.reserves"
                    :autonomy="data.autonomy"
                    :recipes="data.recipes"
                    :raid-days="data.raidDays"
                    :weekly-status="data.weeklyStatus"
                    :paid-count="data.paidCount"
                    :week-range="data.weekRange"
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
    </div>
</template>

<script setup>
import StaticAchievementsCard from './StaticAchievementsCard.vue'
import RosterReadinessBar from './RosterReadinessBar.vue'
import NextRaidCard from './NextRaidCard.vue'
import LogisticsCard from './LogisticsCard.vue'
import UpcomingObjectivesCard from './UpcomingObjectivesCard.vue'

defineProps({
    data: { type: Object, required: true },
})
</script>
