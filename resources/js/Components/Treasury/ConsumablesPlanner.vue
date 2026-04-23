<script>
import ConsumableCard from './ConsumableCard.vue';
import { useTranslation } from "../../composables/useTranslation.js";
const { __ } = useTranslation();

export default {
    name: 'ConsumablesPlanner',
    components: { ConsumableCard },
    props: {
        recipes: { type: Array, default: () => [] },
        raidDays: { type: Number, default: 3 },
        individualPotionPrice: { type: Number, default: 0 },
        individualFlaskPrice: { type: Number, default: 0 },
        totalMembers: { type: Number, default: 0 },
        saveUrl: { type: String, required: true },
        settingsScheduleUrl: { type: String, default: '' },
        canManageTreasury: { type: Boolean, default: false },
        csrfToken: { type: String, required: true },
    },
    data() {
        return {
            localRecipes: this.recipes.map(r => ({ ...r })),
            isSaving: false,
            saveTimeout: null,
        };
    },
    computed: {
        totalCost() {
            return this.localRecipes.reduce((sum, r) => sum + (r.cost * r.quantity * this.raidDays), 0);
        },
        guildTaxPerRaider() {
            const numRaiders = this.totalMembers || 20;
            return Math.ceil(this.totalCost / numRaiders);
        },
        individualCostPerRaider() {
            const potionRecipe = this.localRecipes.find(r => r.name.includes('Potion Cauldron'));
            const flaskRecipe = this.localRecipes.find(r => r.name.includes("Sin'dorei Flasks"));
            const foodRecipe = this.localRecipes.find(r => r.name.includes('Hearty Harandar'));

            const potionsPerCauldron = 20;
            const flasksPerCauldron = 2;

            const potionsNeeded = (potionRecipe ? potionRecipe.quantity : 1) * potionsPerCauldron;
            const flasksNeeded = (flaskRecipe ? flaskRecipe.quantity : 1) * flasksPerCauldron;
            const foodNeeded = (foodRecipe ? foodRecipe.quantity : 4) * 1;

            const weeklyPotions = potionsNeeded * this.raidDays;
            const weeklyFlasks = flasksNeeded * this.raidDays;
            const weeklyFood = foodNeeded * this.raidDays;

            const foodPrice = 500 * 10000;

            return (weeklyPotions * this.individualPotionPrice)
                + (weeklyFlasks * this.individualFlaskPrice)
                + (weeklyFood * foodPrice);
        },
        savings() {
            return this.individualCostPerRaider - this.guildTaxPerRaider;
        },
    },
    watch: {
        localRecipes: {
            deep: true,
            handler() {
                this.autoSave();
            },
        },
    },
    methods: {
        formatGold(copper) {
            return Math.floor(copper / 10000).toLocaleString();
        },
        autoSave() {
            if (this.saveTimeout) clearTimeout(this.saveTimeout);
            this.saveTimeout = setTimeout(async () => {
                this.isSaving = true;
                try {
                    const quantities = {};
                    this.localRecipes.forEach(r => { quantities[r.name] = r.quantity; });
                    const response = await fetch(this.saveUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ quantities }),
                    });
                    if (!response.ok) throw new Error('Save failed');
                    const data = await response.json();
                    window.dispatchEvent(new CustomEvent('consumables-updated', { detail: data }));
                } catch (e) {
                    console.error(e);
                } finally {
                    this.isSaving = false;
                }
            }, 500);
        },
    },
};
</script>

<template>
    <div class="bg-surface-container border border-white/5 rounded-xl overflow-hidden shadow-2xl backdrop-blur-sm">
        <div class="px-6 py-4 border-b border-white/5 bg-surface-container-high flex justify-between items-center">
            <h3 class="font-headline text-xs font-bold text-white uppercase tracking-widest">{{ __('Weekly Consumables Planning') }}</h3>
            <div v-show="isSaving" class="flex items-center gap-2">
                <div class="w-2 h-2 rounded-full bg-yellow-500 animate-pulse"></div>
                <span class="text-4xs text-yellow-500 font-semibold uppercase tracking-wider">{{ __('Saving...') }}</span>
            </div>
        </div>

        <div class="p-4">
            <!-- Static Info -->
            <div class="grid grid-cols-2 gap-3 mb-4">
                <div class="bg-surface-container-lowest/50 p-3 rounded-lg border border-white/5">
                    <div class="text-4xs font-semibold uppercase tracking-wider text-on-surface-variant mb-1">{{ __('Raid Days') }}</div>
                    <div class="text-lg font-headline font-black text-white flex items-center gap-2">
                        <span class="material-symbols-outlined text-yellow-500 text-xs">calendar_month</span>
                        <span>{{ raidDays }}</span>
                        <a v-if="settingsScheduleUrl" :href="settingsScheduleUrl" class="ml-auto text-5xs text-yellow-500 hover:underline uppercase tracking-wider">{{ __('Edit') }}</a>
                    </div>
                </div>
                <div class="bg-surface-container-lowest/50 p-3 rounded-lg border border-white/5">
                    <div class="text-4xs font-semibold uppercase tracking-wider text-on-surface-variant mb-1">{{ __('Total Members') }}</div>
                    <div class="text-lg font-headline font-black text-white flex items-center gap-2">
                        <span class="material-symbols-outlined text-yellow-500 text-xs">groups</span>
                        <span>{{ totalMembers }}/20</span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2 px-3 py-2 mb-2 rounded-md bg-surface-container-lowest/50 border border-white/5">
                <span class="material-symbols-outlined text-yellow-500 text-sm">info</span>
                <p class="text-3xs text-on-surface-variant">
                    {{ __('Quantities below are per single raid night, not per week.') }}
                </p>
            </div>

            <!-- Recipe Cards -->
            <div class="space-y-2">
                <consumable-card
                    v-for="(recipe, index) in localRecipes"
                    :key="index"
                    :recipe="recipe"
                    v-model:quantity="localRecipes[index].quantity"
                    :can-edit="canManageTreasury"
                />
            </div>

            <!-- Calculations -->
            <div class="mt-4 pt-4 border-t border-white/5 flex flex-col gap-3">
                <div class="grid grid-cols-3 gap-3">
                    <div class="flex flex-col">
                        <div class="text-4xs font-semibold uppercase tracking-wider text-on-surface-variant">{{ __('Weekly Total') }}</div>
                        <div class="text-lg font-headline font-black text-white mt-0.5 leading-none tabular-nums">
                            <span>{{ formatGold(totalCost) }}</span>
                            <span class="text-tertiary-dim text-5xs uppercase">G</span>
                        </div>
                    </div>
                    <div class="border-x border-white/5 px-3 flex flex-col">
                        <div class="text-4xs font-semibold uppercase tracking-wider text-on-surface-variant">{{ __('Tax/Raider') }}</div>
                        <div class="text-md font-headline font-bold text-white mt-0.5 leading-none tabular-nums">
                            <span>{{ formatGold(guildTaxPerRaider) }}</span>
                            <span class="text-tertiary-dim text-5xs">G</span>
                        </div>
                    </div>
                    <div class="flex flex-col">
                        <div class="text-4xs font-semibold uppercase tracking-wider text-on-surface-variant leading-tight">{{ __('AH Cost') }}</div>
                        <div class="text-md font-headline font-bold text-error-dim mt-0.5 leading-none tabular-nums">
                            <span>{{ formatGold(individualCostPerRaider) }}</span>
                            <span class="text-tertiary-dim text-5xs">G</span>
                        </div>
                        <div class="mt-1.5" v-show="savings > 0">
                            <span class="inline-flex px-1.5 py-0.5 rounded bg-success-neon/10 text-success-neon text-5xs font-semibold uppercase tracking-wider">
                                {{ __('Save') }} {{ formatGold(savings) }} G
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
