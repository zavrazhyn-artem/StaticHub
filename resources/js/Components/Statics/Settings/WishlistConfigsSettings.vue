<script setup>
import { ref, computed } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
import StyledSelect from '@/Components/UI/StyledSelect.vue';
import ConfirmationModal from '@/Components/UI/ConfirmationModal.vue';
import SettingsTabs from '@/Components/Settings/SettingsTabs.vue';

const { __ } = useTranslation();

const props = defineProps({
    staticName:            { type: String, required: true },
    configs:               { type: Array,  required: true },
    fightStyles:           { type: Array,  required: true },
    ops:                   { type: Array,  required: true },
    upgradeLevels:         { type: Object, required: true },
    storeUrl:              { type: String, required: true },
    updateUrlTemplate:     { type: String, required: true },
    destroyUrlTemplate:    { type: String, required: true },
    profileTabUrl:         { type: String, required: true },
    scheduleTabUrl:        { type: String, required: true },
    discordTabUrl:         { type: String, required: true },
    logsTabUrl:            { type: String, required: true },
    wishlistConfigsTabUrl: { type: String, required: true },
    canManage:             { type: Boolean, default: false },
    csrfToken:             { type: String, required: true },
});

const rows = ref(props.configs.map(c => ({ ...c, _saving: false, _error: '' })));

// Backend stores the camelCase Raidbots fightStyle value; we display
// the spaced human label here without changing what's persisted.
const FIGHT_STYLE_LABELS = {
    Patchwerk:        'Patchwerk',
    DungeonSlice:     'Dungeon Slice',
    TargetDummy:      'Target Dummy',
    ExecutePatchwerk: 'Execute Patchwerk',
    HecticAddCleave:  'Hectic Add Cleave',
    LightMovement:    'Light Movement',
    HeavyMovement:    'Heavy Movement',
    CastingPatchwerk: 'Casting Patchwerk',
    CleaveAdd:        'Cleave Add',
};

const OP_LABELS = {
    less_than: 'is less than',
    at_most:   'is at most',
    is:        'is',
    at_least:  'is at least',
    more_than: 'is more than',
};

const fightStyleOptions = computed(() => props.fightStyles.map(s => ({
    value: s,
    label: FIGHT_STYLE_LABELS[s] ?? s,
})));

const opOptions = computed(() => props.ops.map(o => ({
    value: o,
    label: __(OP_LABELS[o] ?? o),
})));

const levelOptions = (difficulty) => [
    { value: '', label: __('— None') },
    ...((props.upgradeLevels[difficulty] ?? []).map(l => ({ value: l, label: l }))),
];

const blankRow = () => ({
    id: null,
    display_name: '',
    fight_style: 'Patchwerk',
    num_bosses_op: 'is',
    num_bosses: 1,
    fight_length_op: 'at_least',
    fight_length_minutes: 5,
    weight: 1.0,
    require_vault_socket: false,
    require_pi: false,
    voidforged: false,
    allow_expert: false,
    require_upgrade_all_same: false,
    upgrade_level_mythic: '',
    upgrade_level_heroic: '',
    upgrade_level_normal: '',
    upgrade_level_lfr: '',
    _saving: false,
    _error: '',
});

const addRow = () => rows.value.push(blankRow());

const submitRow = async (row) => {
    row._saving = true;
    row._error = '';
    const url = row.id
        ? props.updateUrlTemplate.replace('__ID__', row.id)
        : props.storeUrl;
    const method = row.id ? 'PATCH' : 'POST';
    try {
        const resp = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': props.csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'X-HTTP-Method-Override': method,
            },
            body: JSON.stringify(serialiseRow(row)),
        });
        if (!resp.ok) {
            const data = await resp.json().catch(() => ({}));
            row._error = data.message || `HTTP ${resp.status}`;
            row._saving = false;
            return;
        }
        window.location.reload();
    } catch (e) {
        row._error = String(e.message || e);
        row._saving = false;
    }
};

// Server treats null and "" differently for the upgrade-level fields:
// nullable+Rule::in() rejects "" but accepts null. Coerce empty strings
// back to null on submit.
const serialiseRow = (row) => Object.fromEntries(
    Object.entries(row)
        .filter(([k]) => !k.startsWith('_'))
        .map(([k, v]) => [k, v === '' && k.startsWith('upgrade_level_') ? null : v]),
);

const confirmDelete = ref(null);
const askDelete = (row) => { confirmDelete.value = row; };

const doDelete = async () => {
    if (!confirmDelete.value?.id) {
        rows.value = rows.value.filter(r => r !== confirmDelete.value);
        confirmDelete.value = null;
        return;
    }
    const url = props.destroyUrlTemplate.replace('__ID__', confirmDelete.value.id);
    await fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': props.csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
            'X-HTTP-Method-Override': 'DELETE',
        },
    });
    window.location.reload();
};
</script>

<template>
    <div class="max-w-4xl mx-auto">
        <div class="mb-8">
            <h1 class="text-4xl font-black text-white uppercase tracking-tight font-headline">{{ __('Static Settings') }}</h1>
            <p class="text-on-surface-variant font-medium mt-1 uppercase tracking-widest text-xs">{{ staticName }}</p>
        </div>

        <SettingsTabs
            :profile-url="profileTabUrl"
            :schedule-url="scheduleTabUrl"
            :discord-url="discordTabUrl"
            :logs-url="logsTabUrl"
            :wishlist-configs-url="wishlistConfigsTabUrl"
            active-tab="wishlist-configs"
            :can-manage="canManage"
        />

        <div class="space-y-4">
            <div class="bg-surface-container-low border border-white/5 rounded-xl p-8 shadow-2xl backdrop-blur-sm">
                <div class="flex items-center gap-3 mb-6">
                    <span class="material-symbols-outlined text-slate-400 text-xl">tune</span>
                    <h2 class="font-headline text-sm font-bold text-white uppercase tracking-[0.2em]">{{ __('Allowed Droptimizer configurations') }}</h2>
                </div>

                <p class="text-3xs text-on-surface-variant font-medium uppercase tracking-wider mb-6 normal-case">
                    {{ __('Define which Raidbots Droptimizer setups your raid accepts. When a member imports a wishlist, the report\'s metadata (fight style, length, bosses) is matched against these rows. Item upgrade values get weighted by each matching config\'s weight in the static-wide overview.') }}
                </p>

                <div class="space-y-3">
                    <div v-for="(row, idx) in rows" :key="row.id ?? `new-${idx}`" class="bg-surface-container-highest border border-white/5 rounded-lg p-5 space-y-4" :class="{ 'border-cyan-400/40': row.is_default }">
                        <div v-if="row.is_default" class="flex items-center gap-2 -mt-1 mb-1">
                            <span class="material-symbols-outlined text-cyan-300 text-base">star</span>
                            <span class="text-[10px] font-headline font-bold uppercase tracking-widest text-cyan-200">
                                {{ __('Default — used by the «Run on Raidbots» button on the wishlist tab') }}
                            </span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-[10px] font-headline font-bold uppercase tracking-widest text-on-surface-variant mb-1.5">{{ __('Display name') }}</label>
                                <input v-model="row.display_name" type="text" maxlength="64" :disabled="row.is_default" class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:border-cyan-400/60 focus:outline-none disabled:opacity-60 disabled:cursor-not-allowed" :placeholder="__('Single Target')" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-headline font-bold uppercase tracking-widest text-on-surface-variant mb-1.5">{{ __('Fight style') }}</label>
                                <StyledSelect v-model="row.fight_style" :options="fightStyleOptions" min-width="180px" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-headline font-bold uppercase tracking-widest text-on-surface-variant mb-1.5">{{ __('Weight') }}</label>
                                <input v-model.number="row.weight" type="number" min="0.1" max="10" step="0.1" class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:border-cyan-400/60 focus:outline-none" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-headline font-bold uppercase tracking-widest text-on-surface-variant mb-1.5">{{ __('Number of bosses') }}</label>
                                <div class="flex items-center gap-2">
                                    <StyledSelect v-model="row.num_bosses_op" :options="opOptions" min-width="140px" />
                                    <input v-model.number="row.num_bosses" type="number" min="1" max="30" class="w-20 bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:border-cyan-400/60 focus:outline-none" />
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-headline font-bold uppercase tracking-widest text-on-surface-variant mb-1.5">{{ __('Fight length') }}</label>
                                <div class="flex items-center gap-2">
                                    <StyledSelect v-model="row.fight_length_op" :options="opOptions" min-width="140px" />
                                    <input v-model.number="row.fight_length_minutes" type="number" min="1" max="30" class="w-20 bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:border-cyan-400/60 focus:outline-none" />
                                    <span class="text-xs text-on-surface-variant uppercase tracking-wider">{{ __('min') }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                            <label class="flex items-center gap-2 text-xs text-on-surface-variant cursor-pointer">
                                <input v-model="row.require_vault_socket" type="checkbox" class="w-4 h-4 rounded border-white/20 bg-black/40 text-cyan-400" />
                                {{ __("Require 'Add Vault Socket'") }}
                            </label>
                            <label class="flex items-center gap-2 text-xs text-on-surface-variant cursor-pointer">
                                <input v-model="row.require_pi" type="checkbox" class="w-4 h-4 rounded border-white/20 bg-black/40 text-cyan-400" />
                                {{ __('Require PI') }}
                            </label>
                            <label class="flex items-center gap-2 text-xs text-on-surface-variant cursor-pointer">
                                <input v-model="row.voidforged" type="checkbox" class="w-4 h-4 rounded border-white/20 bg-black/40 text-cyan-400" />
                                {{ __('Voidforged') }}
                            </label>
                            <label class="flex items-center gap-2 text-xs text-on-surface-variant cursor-pointer">
                                <input v-model="row.allow_expert" type="checkbox" class="w-4 h-4 rounded border-white/20 bg-black/40 text-cyan-400" />
                                {{ __('Allow expert mode & custom APL') }}
                            </label>
                        </div>

                        <div class="space-y-2">
                            <p class="text-[10px] font-headline font-bold uppercase tracking-widest text-on-surface-variant">
                                {{ __('Item upgrade level must be') }}
                            </p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <div class="flex items-center gap-2">
                                    <StyledSelect v-model="row.upgrade_level_mythic" :options="levelOptions('mythic')" min-width="160px" />
                                    <span class="text-xs text-on-surface-variant uppercase tracking-wider whitespace-nowrap">{{ __('for Mythic') }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <StyledSelect v-model="row.upgrade_level_heroic" :options="levelOptions('heroic')" min-width="160px" />
                                    <span class="text-xs text-on-surface-variant uppercase tracking-wider whitespace-nowrap">{{ __('for Heroic') }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <StyledSelect v-model="row.upgrade_level_normal" :options="levelOptions('normal')" min-width="160px" />
                                    <span class="text-xs text-on-surface-variant uppercase tracking-wider whitespace-nowrap">{{ __('for Normal') }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <StyledSelect v-model="row.upgrade_level_lfr" :options="levelOptions('lfr')" min-width="160px" />
                                    <span class="text-xs text-on-surface-variant uppercase tracking-wider whitespace-nowrap">{{ __('for LFR') }}</span>
                                </div>
                            </div>
                        </div>

                        <label class="flex items-center gap-2 text-xs text-on-surface-variant cursor-pointer">
                            <input v-model="row.require_upgrade_all_same" type="checkbox" class="w-4 h-4 rounded border-white/20 bg-black/40 text-cyan-400" />
                            {{ __("Require 'Upgrade All Equipped Gear to the Same Level'") }}
                        </label>

                        <div v-if="row._error" class="px-3 py-2 rounded-lg bg-error/10 border border-error/40 text-error text-xs">
                            {{ row._error }}
                        </div>

                        <div class="flex justify-end gap-2 pt-3 border-t border-white/5">
                            <button v-if="!row.is_default" type="button" @click="askDelete(row)" class="px-4 py-2 rounded-sm bg-red-500/10 hover:bg-red-500 text-red-500 hover:text-white text-3xs font-semibold uppercase tracking-wider transition-all active:scale-95 flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-base">delete</span>
                                {{ __('Delete') }}
                            </button>
                            <button type="button" @click="submitRow(row)" :disabled="row._saving" class="px-5 py-2 rounded-sm bg-cyan-500/15 border border-cyan-400/50 text-cyan-100 hover:bg-cyan-500/25 text-3xs font-semibold uppercase tracking-wider transition-all active:scale-95 disabled:opacity-50 flex items-center gap-1.5">
                                <span v-if="row._saving" class="material-symbols-outlined text-base animate-spin">progress_activity</span>
                                <span v-else class="material-symbols-outlined text-base">save</span>
                                {{ row.id ? __('Save') : __('Create') }}
                            </button>
                        </div>
                    </div>

                    <button type="button" @click="addRow" class="w-full py-4 rounded-lg border border-dashed border-white/10 text-on-surface-variant hover:text-cyan-300 hover:border-cyan-400/40 transition flex items-center justify-center gap-2 text-3xs font-semibold uppercase tracking-wider">
                        <span class="material-symbols-outlined text-base">add</span>
                        {{ __('Add new configuration') }}
                    </button>
                </div>
            </div>
        </div>

        <ConfirmationModal
            :show="!!confirmDelete"
            :title="__('Delete configuration?')"
            :description="__('This will remove the config and unlink any wishlists currently matched to it. The wishlists themselves are kept.')"
            :confirm-label="__('Delete')"
            confirm-variant="danger"
            @confirm="doDelete"
            @close="confirmDelete = null"
        />
    </div>
</template>
