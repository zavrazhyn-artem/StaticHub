<x-app-layout>
    <treasury-dashboard
        :static-id="{{ $static->id }}"
        static-name="{{ $static->name }}"
        :initial-target-tax="{{ $targetTax ?: 0 }}"
        :initial-weekly-cost="{{ $weeklyCost ?: 0 }}"
        initial-tax-status="{{ $taxStatus }}"
        initial-tax-description="{{ $taxDescription }}"
        :reserves="{{ $reserves ?: 0 }}"
        :members="{{ json_encode($static->members) }}"
        :weekly-status="{{ json_encode($weeklyStatus) }}"
        :recent-transactions="{{ json_encode($recentTransactions) }}"
        csrf-token="{{ csrf_token() }}"
    >
        <template #planner>
            <x-consumables-planner
                class="h-full flex-1"
                :recipes="$recipes"
                :static="$static"
                :individualPotionPrice="$individualPotionPrice"
                :individualFlaskPrice="$individualFlaskPrice"
                :total_member_slots="$total_member_slots"
            />
        </template>
    </treasury-dashboard>
</x-app-layout>
