<script setup>
import { ref, reactive, computed } from 'vue';
import CharacterSpecPicker from './CharacterSpecPicker.vue';
import { useTranslation } from '@/composables/useTranslation';

const { __ } = useTranslation();

// ---------------------------------------------------------------------------
// Props
// ---------------------------------------------------------------------------
const props = defineProps({
    characters:     { type: Array,  required: true },
    staticId:       { type: Number, required: true },
    initialMain:    { type: [Number, null], default: null },
    initialRaiding: { type: Array,  default: () => [] },
    specializations:{ type: Array,  default: () => [] },
    characterSpecs: { type: Object, default: () => ({}) },
    saveRoute:      { type: String, required: true },
    specSaveRoute:  { type: String, required: true },
    csrfToken:      { type: String, required: true },
});

// ---------------------------------------------------------------------------
// State
// ---------------------------------------------------------------------------
const mainCharId  = ref(props.initialMain);
const raidingIds  = ref([...props.initialRaiding]);
const specs       = reactive({ ...props.characterSpecs });
const saving      = ref(false);
const savedFlash  = ref(false);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
const isMain    = (charId) => mainCharId.value === charId;
const isRaiding = (charId) => raidingIds.value.includes(charId);

const statusLabel = computed(() => {
    if (saving.value)    return __('Saving...');
    if (savedFlash.value) return __('Saved!');
    return __('Changes are saved automatically');
});

const statusColor = computed(() => {
    if (saving.value)    return 'text-yellow-400';
    if (savedFlash.value) return 'text-green-400';
    return 'text-on-surface-variant';
});

const dotColor = computed(() => {
    if (saving.value)    return 'bg-yellow-400 animate-pulse';
    if (savedFlash.value) return 'bg-green-400';
    return 'bg-success-neon animate-pulse';
});

// ---------------------------------------------------------------------------
// Actions
// ---------------------------------------------------------------------------
function setMain(charId) {
    mainCharId.value = charId;
    if (!raidingIds.value.includes(charId)) {
        raidingIds.value.push(charId);
    }
    persist();
}

function toggleRaiding(charId) {
    if (raidingIds.value.includes(charId)) {
        if (charId === mainCharId.value) return; // cannot uncheck main
        raidingIds.value = raidingIds.value.filter(id => id !== charId);
    } else {
        raidingIds.value.push(charId);
    }
    persist();
}

async function persist() {
    saving.value = true;
    try {
        const response = await fetch(props.saveRoute, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': props.csrfToken,
                'Accept':       'application/json',
            },
            body: JSON.stringify({
                main_character_id:  mainCharId.value,
                raiding_characters: raidingIds.value,
            }),
        });
        const data = await response.json();
        if (data.characterSpecs) {
            Object.assign(specs, data.characterSpecs);
        }
        savedFlash.value = true;
        setTimeout(() => { savedFlash.value = false; }, 2000);
    } finally {
        saving.value = false;
    }
}

// ---------------------------------------------------------------------------
// Display helpers
// ---------------------------------------------------------------------------
const CLASS_COLORS = {
    'Death Knight': 'text-[#C41F3B]', 'Demon Hunter': 'text-[#A330C9]',
    'Druid':        'text-[#FF7C0A]', 'Evoker':       'text-[#33937F]',
    'Hunter':       'text-[#ABD473]', 'Mage':         'text-[#3FC7EB]',
    'Monk':         'text-[#00FF98]', 'Paladin':      'text-[#F48CBA]',
    'Priest':       'text-[#FFFFFF]', 'Rogue':        'text-[#FFF468]',
    'Shaman':       'text-[#0070DD]', 'Warlock':      'text-[#8788EE]',
    'Warrior':      'text-[#C69B6D]',
};

function classColor(playableClass) {
    return CLASS_COLORS[playableClass] ?? 'text-white';
}

function specForChar(charId) {
    return specs[charId] ?? { spec_ids: [], main_spec_id: null };
}

function mainSpecName(charId) {
    const data = specForChar(charId);
    if (data.main_spec_id) {
        const spec = props.specializations.find(s => s.id === data.main_spec_id);
        if (spec) return spec.name;
    }
    return null;
}
</script>

<template>
    <div class="bg-surface-container-high rounded-xl border border-white/5 overflow-hidden">

        <!-- Table Header -->
        <div class="bg-black/40 px-8 py-5 border-b border-white/5 grid grid-cols-12 items-center">
            <div class="col-span-4 font-headline text-[10px] font-bold uppercase tracking-[0.2em] text-on-surface-variant">{{ __('Character') }}</div>
            <div class="col-span-2 font-headline text-[10px] font-bold uppercase tracking-[0.2em] text-on-surface-variant text-center">{{ __('Item Level') }}</div>
            <div class="col-span-2 font-headline text-[10px] font-bold uppercase tracking-[0.2em] text-on-surface-variant text-center">{{ __('Realm') }}</div>
            <div class="col-span-1 font-headline text-[10px] font-bold uppercase tracking-[0.2em] text-on-surface-variant text-center">{{ __('Main') }}</div>
            <div class="col-span-1 font-headline text-[10px] font-bold uppercase tracking-[0.2em] text-on-surface-variant text-center">{{ __('Ready For Raid') }}</div>
            <div class="col-span-2 font-headline text-[10px] font-bold uppercase tracking-[0.2em] text-on-surface-variant text-center">{{ __('Specializations') }}</div>
        </div>

        <!-- Table Body -->
        <div class="divide-y divide-white/5">
            <div
                v-for="char in characters"
                :key="char.id"
                class="px-8 py-4 grid grid-cols-12 items-center hover:bg-white/5 transition-colors group"
            >
                <!-- Character -->
                <div class="col-span-4 flex items-center gap-4">
                    <div class="relative">
                        <img
                            :src="char.avatar_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(char.name)}`"
                            class="w-12 h-12 rounded-full object-cover border border-white/5 shadow-lg group-hover:scale-105 transition-transform"
                            :alt="char.name"
                        >
                    </div>
                    <div>
                        <div class="font-headline font-bold text-base tracking-tight leading-none mb-1 flex items-center gap-2"
                             :class="classColor(char.playable_class)">
                            {{ char.name }}
                            <span
                                v-if="isMain(char.id)"
                                class="px-1.5 py-0.5 rounded-full bg-teal-400/20 text-teal-400 border border-teal-400/30 text-[8px] uppercase tracking-widest font-black"
                            >{{ __('Main') }}</span>
                        </div>
                        <div class="text-[10px] text-on-surface-variant font-bold uppercase tracking-widest flex items-center gap-2">
                            <span>{{ __('Level') }} {{ char.level }}</span>
                            <span class="w-1 h-1 rounded-full bg-white/10"></span>
                            <span>{{ mainSpecName(char.id) || char.active_spec || char.playable_class }}</span>
                        </div>
                    </div>
                </div>

                <!-- Item Level -->
                <div class="col-span-2 text-center">
                    <span class="font-headline font-black text-2xl tracking-tighter text-on-surface">
                        {{ char.equipped_item_level ?? '?' }}
                    </span>
                </div>

                <!-- Realm -->
                <div class="col-span-2 text-on-surface-variant text-sm font-medium text-center">
                    {{ char.realm?.name ?? '—' }}
                </div>

                <!-- Main Radio -->
                <div class="col-span-1 flex justify-center">
                    <label class="relative flex items-center justify-center cursor-pointer">
                        <input
                            type="radio"
                            name="main_character_id"
                            :value="char.id"
                            :checked="isMain(char.id)"
                            @change="setMain(char.id)"
                            class="peer sr-only"
                        >
                        <div class="w-5 h-5 rounded-full border-2 border-white/10 peer-checked:border-teal-400 peer-checked:bg-teal-400/20 transition-all"></div>
                        <div class="absolute w-2 h-2 rounded-full bg-teal-400 opacity-0 peer-checked:opacity-100 transition-all"></div>
                    </label>
                </div>

                <!-- Raiding Checkbox -->
                <div class="col-span-1 flex justify-center">
                    <label class="relative flex items-center justify-center cursor-pointer">
                        <input
                            type="checkbox"
                            :checked="isRaiding(char.id)"
                            @change="toggleRaiding(char.id)"
                            class="peer sr-only"
                        >
                        <div class="w-5 h-5 rounded border-2 border-white/10 peer-checked:border-success-neon peer-checked:bg-success-neon/20 transition-all"></div>
                        <span
                            class="material-symbols-outlined absolute text-success-neon text-sm opacity-0 peer-checked:opacity-100 transition-all"
                            style="font-variation-settings: 'FILL' 1;"
                        >check</span>
                    </label>
                </div>

                <!-- Specializations -->
                <div class="col-span-2 flex justify-center">
                    <CharacterSpecPicker
                        :character-id="char.id"
                        :static-id="staticId"
                        :character-class="char.playable_class"
                        :all-specs="specializations"
                        :initial-spec-ids="specForChar(char.id).spec_ids"
                        :initial-main-spec-id="specForChar(char.id).main_spec_id ?? null"
                        :save-route="specSaveRoute"
                        :csrf-token="csrfToken"
                    />
                </div>
            </div>
        </div>

        <!-- Footer: auto-save status -->
        <div class="p-6 bg-black/20 text-center border-t border-white/5">
            <p class="text-[10px] font-bold uppercase tracking-[0.3em] flex items-center justify-center gap-2"
               :class="statusColor">
                <span class="w-1.5 h-1.5 rounded-full" :class="dotColor"></span>
                {{ statusLabel }}
            </p>
        </div>

    </div>
</template>
