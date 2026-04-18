<script setup>
import { ref, computed, watch } from 'vue';
import GlassModal from '../UI/GlassModal.vue';

const props = defineProps({
    show:                { type: Boolean, required: true },
    userCharacters:      { type: Array,   required: true },
    selectedCharacterId: { type: Number,  default: null },
    currentAttendance:   { type: Object,  default: null },
    csrfToken:           { type: String,  required: true },
    rsvpRoute:           { type: String,  required: true },
    // Specs per character: { characterId: [{ id, name, role, icon_url, is_main }] }
    characterSpecs:      { type: Object,  default: () => ({}) },
});
const emit = defineEmits(['close']);

const rsvpCharacterId = ref(props.selectedCharacterId);
const rsvpStatus      = ref(props.currentAttendance?.status || 'present');
const rsvpComment     = ref(props.currentAttendance?.comment || '');

// Available specs for selected character
const availableSpecs = computed(() => {
    if (!rsvpCharacterId.value) return [];
    return props.characterSpecs[rsvpCharacterId.value] ?? [];
});

const roleBadgeClass = {
    tank:  'bg-blue-500/20 text-blue-300 border-blue-500/30',
    heal:  'bg-green-500/20 text-green-300 border-green-500/30',
    mdps:  'bg-red-500/20 text-red-300 border-red-500/30',
    rdps:  'bg-purple-500/20 text-purple-300 border-purple-500/30',
};
const roleLabel = { tank: 'Tank', heal: 'Heal', mdps: 'Melee', rdps: 'Ranged' };

// Initialize spec: prefer already-chosen RSVP spec, fall back to main spec
const getInitialSpecId = () => {
    if (props.currentAttendance?.spec_id) return props.currentAttendance.spec_id;
    const specs = props.characterSpecs[props.selectedCharacterId] ?? [];
    return specs.find(s => s.is_main)?.id ?? specs[0]?.id ?? null;
};
const rsvpSpecId = ref(getInitialSpecId());

// Only reset spec when character changes (not on initial mount)
watch(rsvpCharacterId, (newId, oldId) => {
    if (oldId === undefined) return;
    const specs = props.characterSpecs[newId] ?? [];
    const mainSpec = specs.find(s => s.is_main);
    rsvpSpecId.value = mainSpec?.id ?? specs[0]?.id ?? null;
});
</script>

<template>
    <GlassModal :show="show" backdrop="bg-black/60" z-index="z-[110]" @close="emit('close')">
        <div class="p-8">
            <div class="flex items-center justify-between mb-8">
                <h2 class="text-xl font-black text-white uppercase tracking-tight font-headline">{{ __('Join Raid') }}</h2>
                <button @click="emit('close')" class="text-on-surface-variant hover:text-white transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <form :action="rsvpRoute" method="POST" class="space-y-6">
                <input type="hidden" name="_token" :value="csrfToken">

                <!-- Character select -->
                <div class="space-y-2">
                    <label class="text-3xs font-bold uppercase tracking-wider text-on-surface-variant ml-2">{{ __('Character') }}</label>
                    <select
                        name="character_id"
                        v-model="rsvpCharacterId"
                        class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm font-bold text-white focus:ring-2 focus:ring-green-500 outline-none appearance-none cursor-pointer"
                    >
                        <option
                            v-for="char in userCharacters"
                            :key="char.id"
                            :value="char.id"
                            class="bg-surface-container-highest"
                        >{{ char.name }} ({{ char.playable_class }})</option>
                    </select>
                </div>

                <!-- Spec select (only if specs are available) -->
                <div v-if="availableSpecs.length > 0" class="space-y-2">
                    <label class="text-3xs font-bold uppercase tracking-wider text-on-surface-variant ml-2">{{ __('Specialization') }}</label>
                    <div class="grid grid-cols-3 gap-2">
                        <button
                            v-for="spec in availableSpecs"
                            :key="spec.id"
                            type="button"
                            @click="rsvpSpecId = spec.id"
                            class="relative flex flex-col items-center gap-1 p-2 rounded-xl border transition-all"
                            :class="rsvpSpecId === spec.id
                                ? 'border-green-500/60 bg-green-500/10'
                                : 'border-white/10 bg-white/5 hover:border-white/20'"
                        >
                            <!-- Main spec star -->
                            <span
                                v-if="spec.is_main"
                                class="absolute top-1 left-1 w-3.5 h-3.5 bg-yellow-400 rounded-full flex items-center justify-center shadow-sm"
                                :title="__('Main spec')"
                            >
                                <span class="material-symbols-outlined text-black leading-none" style="font-size: 9px;">star</span>
                            </span>

                            <img :src="spec.icon_url" :alt="spec.name" class="w-8 h-8 rounded-lg object-cover">
                            <span class="text-4xs font-semibold text-white">{{ spec.name }}</span>
                            <span
                                class="text-5xs font-bold uppercase tracking-wide px-1 py-0.5 rounded-full border"
                                :class="roleBadgeClass[spec.role] ?? 'bg-white/10 text-white/60 border-white/10'"
                            >{{ roleLabel[spec.role] ?? spec.role }}</span>
                        </button>
                    </div>
                    <input type="hidden" name="spec_id" :value="rsvpSpecId">
                </div>

                <!-- Status selector -->
                <div class="space-y-2">
                    <label class="text-3xs font-bold uppercase tracking-wider text-on-surface-variant ml-2">{{ __('Attendance Status') }}</label>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            v-for="status in ['present', 'absent', 'tentative', 'late']"
                            :key="status"
                            @click="rsvpStatus = status"
                            class="px-4 py-2 border rounded-xl text-3xs font-bold uppercase tracking-wider transition-all"
                            :class="rsvpStatus === status
                                ? 'bg-green-600/20 border-green-500 text-green-400'
                                : 'bg-white/5 border-white/10 text-on-surface-variant'"
                        >{{ status }}</button>
                        <input type="hidden" name="status" :value="rsvpStatus">
                    </div>
                </div>

                <!-- Comment -->
                <div class="space-y-2">
                    <label class="text-3xs font-bold uppercase tracking-wider text-on-surface-variant ml-2">{{ __('Comment (Optional)') }}</label>
                    <input
                        type="text"
                        name="comment"
                        v-model="rsvpComment"
                        :placeholder="__('e.g., Might be 10m late')"
                        class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:ring-2 focus:ring-green-500 outline-none"
                    >
                </div>

                <button
                    type="submit"
                    class="w-full bg-green-600 hover:bg-green-500 text-white py-4 rounded-xl font-headline text-xs font-black uppercase tracking-widest transition-all shadow-lg shadow-green-900/20"
                >{{ __('Confirm Attendance') }}</button>
            </form>
        </div>
    </GlassModal>
</template>
