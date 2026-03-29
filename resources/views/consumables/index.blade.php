<x-app-layout>
    <div x-data="{
        raidDays: {{ count($static?->raid_days ?? [1, 2, 3]) }},
        numRaiders: {{ $total_member_slots }},
        individualPotionPrice: {{ $individualPotionPrice }},
        individualFlaskPrice: {{ $individualFlaskPrice }},
        recipes: {{ $recipes->map(fn($r) => ['name' => $r->name, 'cost' => $r->crafting_cost, 'quantity' => $r->default_quantity, 'icon' => $r->icon])->toJson() }},
        get totalCost() {
            return this.recipes.reduce((sum, r) => sum + (r.cost * r.quantity * this.raidDays), 0);
        },
        get guildTaxPerRaider() {
            return Math.ceil(this.totalCost / (this.numRaiders || 1));
        },
        get individualCostPerRaider() {
            // AH Cost logic:
            // Voidlight Potion Cauldron (ID: 435133) provides 20 potions per person
            // Cauldron of Sin'dorei Flasks (ID: 435132) provides 2 flasks per person
            // Hearty Harandar Celebration (ID: 411831) provides 1 food buff

            const potionRecipe = this.recipes.find(r => r.name.includes('Potion Cauldron'));
            const flaskRecipe = this.recipes.find(r => r.name.includes('Sin\'dorei Flasks'));
            const foodRecipe = this.recipes.find(r => r.name.includes('Hearty Harandar'));

            const potionsPerCauldron = 20;
            const flasksPerCauldron = 2;
            const foodPerFeast = 1; // Assuming 1 feast used per food buff needed (standard)

            const potionsNeeded = (potionRecipe ? potionRecipe.quantity : 1) * potionsPerCauldron;
            const flasksNeeded = (flaskRecipe ? flaskRecipe.quantity : 1) * flasksPerCauldron;
            const foodNeeded = (foodRecipe ? foodRecipe.quantity : 4) * foodPerFeast;

            const weeklyPotions = potionsNeeded * this.raidDays;
            const weeklyFlasks = flasksNeeded * this.raidDays;
            const weeklyFood = foodNeeded * this.raidDays;

            const foodPrice = 500 * 10000; // 500g per food estimate

            return (weeklyPotions * this.individualPotionPrice) +
                   (weeklyFlasks * this.individualFlaskPrice) +
                   (weeklyFood * foodPrice);
        },
        get savings() {
            return this.individualCostPerRaider - this.guildTaxPerRaider;
        },
        formatGold(copper) {
            return Math.floor(copper / 10000).toLocaleString();
        }
    }">
        <header class="mb-6 flex items-end justify-between">
            <div>
                <span class="text-on-surface-variant font-headline text-xs font-bold uppercase tracking-widest">Treasury</span>
                <h1 class="font-headline text-3xl font-extrabold tracking-tight">Consumables <span class="text-primary font-normal text-lg ml-2">/ Weekly Planning</span></h1>
            </div>
            <div class="flex items-center gap-4">
                @if(session('success'))
                    <span class="text-success-neon text-xs font-bold animate-fade-out">{{ session('success') }}</span>
                @endif
                <button type="submit" form="consumables-form" class="bg-primary hover:bg-primary-dim text-on-primary px-6 py-2 rounded-lg font-headline font-bold text-xs uppercase tracking-widest transition-all flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">save</span>
                    Save Configuration
                </button>
            </div>
        </header>

        <form id="consumables-form" action="{{ route('consumables.store') }}" method="POST">
            @csrf
            <div class="bg-surface-container-low p-6 rounded-xl border border-white/5">
                <!-- Static Info (Read Only from Settings) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div class="bg-surface-container-lowest/50 p-4 rounded-lg border border-white/5">
                        <div class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-1">Raid Days (from Settings)</div>
                        <div class="text-xl font-headline font-black text-white flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary text-sm">calendar_month</span>
                            {{ count($static?->raid_days ?? []) }} Days / Week
                            <a href="{{ route('statics.settings.schedule', $static) }}" class="ml-auto text-[10px] text-primary hover:underline uppercase tracking-widest">Edit</a>
                        </div>
                    </div>
                    <div class="bg-surface-container-lowest/50 p-4 rounded-lg border border-white/5">
                        <div class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-1">Total Members</div>
                        <div class="text-xl font-headline font-black text-white flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary text-sm">groups</span>
                            {{ $active_member_count }} / {{ $total_member_slots }} Active Raiders
                        </div>
                    </div>
                </div>

                <!-- Recipe Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach($recipes as $index => $recipe)
                        <x-consumable-card
                            :recipe="$recipe"
                            :icon="$recipe->display_icon"
                            :icon_url="$recipe->display_icon_url"
                            :color="$recipe->display_color"
                            x-model.number="recipes[{{ $index }}].quantity"
                            name="quantities[{{ $recipe->name }}]"
                        />
                    @endforeach
                </div>

                <!-- Calculations -->
                <div class="mt-8 pt-8 border-t border-white/5 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Grand Total Weekly Cost</div>
                        <div class="text-2xl font-headline font-black text-white mt-1"><span x-text="formatGold(totalCost)"></span> <span class="text-tertiary-dim text-sm uppercase">Gold</span></div>
                    </div>
                    <div class="md:border-x border-white/5 md:px-6">
                        <div class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Guild Tax (Per Player)</div>
                        <div class="text-xl font-headline font-bold text-white mt-1"><span x-text="formatGold(guildTaxPerRaider)"></span> <span class="text-tertiary-dim text-xs">G</span></div>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">AH Cost (Per Player)</div>
                        <div class="text-xl font-headline font-bold text-error-dim mt-1"><span x-text="formatGold(individualCostPerRaider)"></span> <span class="text-tertiary-dim text-xs">G</span></div>
                        <div class="inline-flex items-center gap-1 mt-2 px-2 py-0.5 rounded bg-success-neon/10 text-success-neon text-[9px] font-bold uppercase tracking-widest" x-show="savings > 0">
                            <span class="material-symbols-outlined text-[12px]">trending_down</span>
                            You save <span x-text="formatGold(savings)"></span> gold/week
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
