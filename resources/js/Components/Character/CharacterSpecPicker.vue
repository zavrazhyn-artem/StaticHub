<script setup>
import { ref, computed, watch } from 'vue';
import SpecPicker from '../UI/SpecPicker.vue';

const props = defineProps({
    characterId:       { type: Number, required: true },
    staticId:          { type: Number, required: true },
    characterClass:    { type: String, default: '' },
    allSpecs:          { type: Array,  default: () => [] },
    initialSpecIds:    { type: Array,  default: () => [] },
    initialMainSpecId: { type: [Number, null], default: null },
    saveRoute:         { type: String, required: true },
    csrfToken:         { type: String, required: true },
});

// ---------------------------------------------------------------------------
// State
// ---------------------------------------------------------------------------
const showModal      = ref(false);
const saving         = ref(false);
const savedFlash     = ref(false);
const errorFlash     = ref(false);
const selectedSpecIds = ref([...props.initialSpecIds]);
const mainSpecId      = ref(props.initialMainSpecId);

let saveTimer = null;

// ---------------------------------------------------------------------------
// Computed
// ---------------------------------------------------------------------------
const selectedSpecs = computed(() =>
    props.allSpecs.filter(s => selectedSpecIds.value.includes(s.id))
);

const orderedSelectedSpecs = computed(() => {
    const main = selectedSpecs.value.find(s => s.id === mainSpecId.value);
    const rest = selectedSpecs.value.filter(s => s.id !== mainSpecId.value);
    return main ? [main, ...rest] : rest;
});

// ---------------------------------------------------------------------------
// Auto-save on any change (debounced 400 ms)
// ---------------------------------------------------------------------------
watch([selectedSpecIds, mainSpecId], () => {
    if (!mainSpecId.value || selectedSpecIds.value.length === 0) return;
    clearTimeout(saveTimer);
    saveTimer = setTimeout(saveSpecs, 400);
}, { deep: true });

async function saveSpecs() {
    if (!mainSpecId.value || selectedSpecIds.value.length === 0) return;

    saving.value = true;
    errorFlash.value = false;
    try {
        const res = await fetch(props.saveRoute, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':  props.csrfToken,
                'Accept':        'application/json',
            },
            body: JSON.stringify({
                character_id: props.characterId,
                static_id:    props.staticId,
                spec_ids:     selectedSpecIds.value,
                main_spec_id: mainSpecId.value,
            }),
        });

        if (res.ok) {
            savedFlash.value = true;
            setTimeout(() => { savedFlash.value = false; }, 1500);
        } else {
            errorFlash.value = true;
            setTimeout(() => { errorFlash.value = false; }, 3000);
        }
    } catch {
        errorFlash.value = true;
        setTimeout(() => { errorFlash.value = false; }, 3000);
    } finally {
        saving.value = false;
    }
}

// ---------------------------------------------------------------------------
// Modal
// ---------------------------------------------------------------------------
function openModal() {
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
}
</script>

<template>
    <!-- ── Collapsed display ─────────────────────────────────────────────── -->
    <div class="flex items-center gap-1 flex-wrap">

        <!-- Spec icon chips -->
        <button
            v-if="orderedSelectedSpecs.length > 0"
            type="button"
            @click="openModal"
            class="flex items-center gap-1 flex-wrap hover:opacity-80 transition-opacity"
            :title="__('Edit specializations')"
        >
            <span
                v-for="spec in orderedSelectedSpecs"
                :key="spec.id"
                class="relative inline-block"
            >
                <img
                    :src="spec.icon_url"
                    :alt="spec.name"
                    class="w-7 h-7 rounded-md object-cover"
                    :title="spec.name"
                >
                <span
                    v-if="spec.id === mainSpecId"
                    class="absolute -top-1 -left-1 w-3.5 h-3.5 bg-yellow-400 rounded-full flex items-center justify-center shadow-sm"
                >
                    <span class="material-symbols-outlined text-black leading-none" style="font-size: 9px;">star</span>
                </span>
            </span>
        </button>

        <!-- Not set placeholder -->
        <button
            v-else
            type="button"
            @click="openModal"
            class="text-3xs text-on-surface-variant italic hover:text-white transition-colors"
        >{{ __('Not set') }}</button>

        <!-- Status indicators -->
        <span v-if="saving" class="material-symbols-outlined text-sm text-on-surface-variant animate-spin ml-1">progress_activity</span>
        <span v-else-if="savedFlash" class="material-symbols-outlined text-sm text-green-400 ml-1">check</span>
        <span v-else-if="errorFlash" class="material-symbols-outlined text-sm text-red-400 ml-1">close</span>
    </div>

    <!-- ── Modal ─────────────────────────────────────────────────────────── -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-150"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-150"
            leave-to-class="opacity-0"
        >
            <div
                v-if="showModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                @click.self="closeModal"
            >
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>

                <!-- Panel -->
                <div class="relative z-10 w-full max-w-sm bg-surface-container-high rounded-2xl border border-white/10 shadow-2xl flex flex-col overflow-hidden">

                    <!-- Header -->
                    <div class="flex items-center justify-between px-5 py-4 border-b border-white/5">
                        <h2 class="text-sm font-black uppercase tracking-widest text-white">
                            {{ __('Edit specializations') }}
                        </h2>
                        <div class="flex items-center gap-3">
                            <!-- Inline save status -->
                            <span v-if="saving" class="material-symbols-outlined text-sm text-on-surface-variant animate-spin">progress_activity</span>
                            <span v-else-if="savedFlash" class="text-3xs text-green-400 font-semibold uppercase tracking-wider">{{ __('Saved!') }}</span>
                            <span v-else-if="errorFlash" class="text-3xs text-red-400 font-semibold uppercase tracking-wider">{{ __('Error') }}</span>

                            <button
                                type="button"
                                @click="closeModal"
                                class="text-on-surface-variant hover:text-white transition-colors"
                            >
                                <span class="material-symbols-outlined text-lg">close</span>
                            </button>
                        </div>
                    </div>

                    <!-- Body -->
                    <div class="p-5 overflow-y-auto max-h-[70vh]">
                        <SpecPicker
                            :all-specs="allSpecs"
                            :character-class="characterClass"
                            v-model:selected-spec-ids="selectedSpecIds"
                            v-model:main-spec-id="mainSpecId"
                        />
                    </div>

                    <!-- Footer: hint instead of buttons -->
                    <div class="px-5 py-3 border-t border-white/5 text-center">
                        <p class="text-4xs text-on-surface-variant/60 uppercase tracking-wider font-semibold">
                            {{ __('Changes are saved automatically') }}
                        </p>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
