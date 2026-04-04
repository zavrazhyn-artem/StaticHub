<x-app-layout>
    <treasury-dashboard
        :static-id="{{ $static->id }}"
        static-name="{{ $static->name }}"
        :initial-target-tax="{{ $targetTax ?: 0 }}"
        :initial-weekly-cost="{{ $weeklyCost ?: 0 }}"
        initial-tax-status="{{ $taxStatus }}"
        initial-tax-description="{{ $taxDescription }}"
        initial-tax-class="{{ $taxClass }}"
        :reserves="{{ $reserves ?: 0 }}"
        :members="{{ json_encode($static->members) }}"
        :weekly-status="{{ json_encode($weeklyStatus) }}"
        :recent-transactions="{{ json_encode($recentTransactions) }}"
        csrf-token="{{ csrf_token() }}"
    >
        <template #planner>
            <consumables-planner
                class="h-full flex-1"
                :recipes="{{ json_encode($recipes->map(fn($r) => ['name' => $r->name, 'cost' => $r->crafting_cost, 'quantity' => $r->quantity, 'icon' => $r->display_icon, 'icon_url' => $r->display_icon_url, 'color' => $r->display_color])) }}"
                :raid-days="{{ count($static->raid_days ?? [1, 2, 3]) }}"
                :individual-potion-price="{{ $individualPotionPrice }}"
                :individual-flask-price="{{ $individualFlaskPrice }}"
                save-url="{{ route('consumables.store', $static) }}"
                settings-schedule-url="{{ route('statics.settings.schedule', $static) }}"
                csrf-token="{{ csrf_token() }}"
            />
        </template>
    </treasury-dashboard>
</x-app-layout>
