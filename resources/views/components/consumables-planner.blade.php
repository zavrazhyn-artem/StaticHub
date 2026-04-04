@props([
    'recipes',
    'static',
    'individualPotionPrice',
    'individualFlaskPrice',
    'total_member_slots'
])

<div x-data="{
    raidDays: {{ count($static?->raid_days ?? [1, 2, 3]) }},
    numRaiders: 20,
    individualPotionPrice: {{ $individualPotionPrice }},
    individualFlaskPrice: {{ $individualFlaskPrice }},
    recipes: {{ $recipes->map(fn($r) => ['name' => $r->name, 'cost' => $r->crafting_cost, 'quantity' => $r->quantity, 'icon' => $r->icon])->toJson() }},
    isSaving: false,
    saveTimeout: null,
    autoSave() {
        if (this.saveTimeout) clearTimeout(this.saveTimeout);
        this.saveTimeout = setTimeout(async () => {
            this.isSaving = true;
            try {
                const quantities = {};
                this.recipes.forEach(r => {
                    quantities[r.name] = r.quantity;
                });

                const response = await fetch('{{ route('consumables.store', $static) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ quantities })
                });

                if (!response.ok) throw new Error('Save failed');
            } catch (e) {
                console.error(e);
            } finally {
                this.isSaving = false;
            }
        }, 500);
    },
    init() {
        this.$watch('recipes', () => {
            this.autoSave();
        }, { deep: true });
    },
    get totalCost() {
        const total = this.recipes.reduce((sum, r) => sum + (r.cost * r.quantity * this.raidDays), 0);
        this.$dispatch('consumables-updated', { totalCost: total, taxPerRaider: Math.ceil(total / (this.numRaiders || 1)) });
        return total;
    },
    get guildTaxPerRaider() {
        return Math.ceil(this.totalCost / (this.numRaiders || 1));
    },
    get individualCostPerRaider() {
        const potionRecipe = this.recipes.find(r => r.name.includes('Potion Cauldron'));
        const flaskRecipe = this.recipes.find(r => r.name.includes('Sin\'dorei Flasks'));
        const foodRecipe = this.recipes.find(r => r.name.includes('Hearty Harandar'));

        const potionsPerCauldron = 20;
        const flasksPerCauldron = 2;
        const foodPerFeast = 1;

        const potionsNeeded = (potionRecipe ? potionRecipe.quantity : 1) * potionsPerCauldron;
        const flasksNeeded = (flaskRecipe ? flaskRecipe.quantity : 1) * flasksPerCauldron;
        const foodNeeded = (foodRecipe ? foodRecipe.quantity : 4) * foodPerFeast;

        const weeklyPotions = potionsNeeded * this.raidDays;
        const weeklyFlasks = flasksNeeded * this.raidDays;
        const weeklyFood = foodNeeded * this.raidDays;

        const foodPrice = 500 * 10000;

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
    <div class="bg-surface-container border border-white/5 rounded-xl overflow-hidden shadow-2xl backdrop-blur-sm">
        <div class="px-6 py-4 border-b border-white/5 bg-surface-container-high flex justify-between items-center">
            <h3 class="font-headline text-xs font-bold text-white uppercase tracking-widest">Weekly Consumables Planning</h3>
            <div x-show="isSaving" class="flex items-center gap-2" x-transition>
                <div class="w-2 h-2 rounded-full bg-primary animate-pulse"></div>
                <span class="text-[9px] text-primary font-bold uppercase tracking-widest">Saving...</span>
            </div>
        </div>

        <form id="consumables-form" action="{{ route('consumables.store', $static) }}" method="POST" class="p-4">
            @csrf
            <!-- Static Info (from Settings) -->
            <div class="grid grid-cols-2 gap-3 mb-4">
                <div class="bg-surface-container-lowest/50 p-3 rounded-lg border border-white/5">
                    <div class="text-[9px] font-bold uppercase tracking-widest text-on-surface-variant mb-1">Raid Days</div>
                    <div class="text-lg font-headline font-black text-white flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-xs">calendar_month</span>
                        <span x-text="raidDays"></span>
                        <a href="{{ route('statics.settings.schedule', $static) }}" class="ml-auto text-[8px] text-primary hover:underline uppercase tracking-widest">Edit</a>
                    </div>
                </div>
                <div class="bg-surface-container-lowest/50 p-3 rounded-lg border border-white/5">
                    <div class="text-[9px] font-bold uppercase tracking-widest text-on-surface-variant mb-1">Total Members</div>
                    <div class="text-lg font-headline font-black text-white flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-xs">groups</span>
                        <span>1/20</span>
                    </div>
                </div>
            </div>

            <!-- Recipe Cards -->
            <div class="space-y-2">
                @foreach($recipes as $index => $recipe)
                    <x-consumable-card
                        :recipe="$recipe"
                        :icon="$recipe->display_icon"
                        :icon_url="$recipe->display_icon_url"
                        :color="$recipe->display_color"
                        x-model.number="recipes[{{ $index }}].quantity"
                        name="quantities[{{ $recipe->name }}]"
                        :value="$recipe->quantity"
                    />
                @endforeach
            </div>

            <!-- Calculations -->
            <div class="mt-4 pt-4 border-t border-white/5 flex flex-col gap-3">
                <div class="grid grid-cols-3 gap-3">
                    <div class="flex flex-col">
                        <div class="text-[9px] font-bold uppercase tracking-widest text-on-surface-variant">Weekly Total</div>
                        <div class="text-lg font-headline font-black text-white mt-0.5 leading-none">
                            <span x-text="formatGold(totalCost)"></span>
                            <span class="text-tertiary-dim text-[8px] uppercase">G</span>
                        </div>
                    </div>
                    <div class="border-x border-white/5 px-3 flex flex-col">
                        <div class="text-[9px] font-bold uppercase tracking-widest text-on-surface-variant">Tax/Raider</div>
                        <div class="text-md font-headline font-bold text-white mt-0.5 leading-none">
                            <span x-text="formatGold(guildTaxPerRaider)"></span>
                            <span class="text-tertiary-dim text-[8px]">G</span>
                        </div>
                    </div>
                    <div class="flex flex-col">
                        <div class="text-[9px] font-bold uppercase tracking-widest text-on-surface-variant leading-tight">AH Cost</div>
                        <div class="text-md font-headline font-bold text-error-dim mt-0.5 leading-none">
                            <span x-text="formatGold(individualCostPerRaider)"></span>
                            <span class="text-tertiary-dim text-[8px]">G</span>
                        </div>
                        <div class="mt-1.5" x-show="savings > 0">
                            <span class="inline-flex px-1.5 py-0.5 rounded bg-success-neon/10 text-success-neon text-[8px] font-bold uppercase tracking-widest" x-text="'Save ' + formatGold(savings) + ' G'"></span>
                        </div>
                    </div>
                </div>

            </div>
        </form>
    </div>
</div>
