<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Consumables') }}
        </h2>
    </x-slot>

    <div class="py-12" x-data="{
        raidDays: 3,
        numRaiders: 20,
        individualPotionPrice: {{ $individualPotionPrice }},
        individualFlaskPrice: {{ $individualFlaskPrice }},
        recipes: {{ $recipes->map(fn($r) => ['name' => $r->name, 'cost' => $r->crafting_cost, 'quantity' => $r->default_quantity, 'icon' => $r->icon])->toJson() }},
        get totalCost() {
            return this.recipes.reduce((sum, r) => sum + (r.cost * r.quantity * this.raidDays), 0);
        },
        get guildTaxPerRaider() {
            return this.totalCost / (this.numRaiders || 1);
        },
        get individualCostPerRaider() {
            const potionsPerWeek = 20 * this.raidDays;
            const flasksPerWeek = 2 * this.raidDays;
            return (potionsPerWeek * this.individualPotionPrice) + (flasksPerWeek * this.individualFlaskPrice);
        },
        get savings() {
            return this.individualCostPerRaider - this.guildTaxPerRaider;
        },
        formatGold(copper) {
            return Math.floor(copper / 10000);
        },
        formatSilver(copper) {
            return Math.floor((copper % 10000) / 100);
        },
        formatCopper(copper) {
            return Math.floor(copper % 100);
        }
    }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="mb-8">
                    <h3 class="text-2xl font-bold mb-4">Weekly Raid Settings</h3>
                    <div class="flex flex-wrap items-center gap-6">
                        <div class="flex items-center space-x-4">
                            <label for="raidDays" class="font-medium">Raid Days per Week:</label>
                            <input type="number" id="raidDays" x-model.number="raidDays" min="1" max="7" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 w-20">
                        </div>
                        <div class="flex items-center space-x-4">
                            <label for="numRaiders" class="font-medium">Number of Raiders:</label>
                            <input type="number" id="numRaiders" x-model.number="numRaiders" min="1" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 w-24">
                        </div>
                    </div>
                </div>

                <div class="mb-8">
                    <h3 class="text-xl font-bold mb-4">Consumables</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <template x-for="(recipe, index) in recipes" :key="index">
                            <div class="border rounded-lg p-4 bg-gray-50">
                                <div class="flex items-center mb-4">
                                    <img :src="recipe.icon" :alt="recipe.name" class="w-10 h-10 mr-3 rounded" x-show="recipe.icon">
                                    <span class="font-bold text-lg" x-text="recipe.name"></span>
                                </div>
                                <div class="mb-4">
                                    <span class="text-sm text-gray-600 block">Crafting Cost (per unit):</span>
                                    <span class="text-yellow-600 font-bold" x-text="formatGold(recipe.cost) + 'g'"></span>
                                    <span class="text-gray-400 font-bold" x-text="formatSilver(recipe.cost) + 's'"></span>
                                    <span class="text-orange-400 font-bold" x-text="formatCopper(recipe.cost) + 'c'"></span>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantity per Raid Day:</label>
                                    <input type="number" x-model.number="recipe.quantity" min="0" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 w-full">
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="mt-8 pt-6 border-t border-gray-200">
                    <div class="flex flex-col items-center mb-8">
                        <h3 class="text-xl font-bold mb-2">Grand Total Weekly Cost</h3>
                        <div class="text-3xl font-black">
                            <span class="text-yellow-600" x-text="formatGold(totalCost) + 'g'"></span>
                            <span class="text-gray-400" x-text="formatSilver(totalCost) + 's'"></span>
                            <span class="text-orange-400" x-text="formatCopper(totalCost) + 'c'"></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-4xl mx-auto">
                        <div class="bg-gray-50 p-6 rounded-xl border-2 border-dashed border-gray-200">
                            <h4 class="text-lg font-bold mb-4 text-center">Guild Tax (Per Player)</h4>
                            <div class="text-2xl font-bold text-center">
                                <span class="text-yellow-600" x-text="formatGold(guildTaxPerRaider) + 'g'"></span>
                                <span class="text-gray-400" x-text="formatSilver(guildTaxPerRaider) + 's'"></span>
                                <span class="text-orange-400" x-text="formatCopper(guildTaxPerRaider) + 'c'"></span>
                            </div>
                        </div>

                        <div class="bg-gray-50 p-6 rounded-xl border-2 border-dashed border-gray-200">
                            <h4 class="text-lg font-bold mb-1 text-center">AH Cost (Per Player)</h4>
                            <p class="text-xs text-gray-500 text-center mb-4">(Assumes 2 flasks & 20 pots per raid day)</p>
                            <div class="text-2xl font-bold text-center">
                                <span class="text-yellow-600" x-text="formatGold(individualCostPerRaider) + 'g'"></span>
                                <span class="text-gray-400" x-text="formatSilver(individualCostPerRaider) + 's'"></span>
                                <span class="text-orange-400" x-text="formatCopper(individualCostPerRaider) + 'c'"></span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 text-center">
                        <template x-if="savings > 0">
                            <div class="inline-block bg-green-100 text-green-800 px-6 py-3 rounded-full font-bold text-xl border-2 border-green-200">
                                You save
                                <span x-text="formatGold(savings) + 'g ' + formatSilver(savings) + 's ' + formatCopper(savings) + 'c'"></span>
                                by raiding with the Guild!
                            </div>
                        </template>
                        <template x-if="savings <= 0">
                            <div class="inline-block bg-orange-100 text-orange-800 px-6 py-3 rounded-full font-bold text-xl border-2 border-orange-200">
                                AH is currently cheaper by
                                <span x-text="formatGold(Math.abs(savings)) + 'g ' + formatSilver(Math.abs(savings)) + 's ' + formatCopper(Math.abs(savings)) + 'c'"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
