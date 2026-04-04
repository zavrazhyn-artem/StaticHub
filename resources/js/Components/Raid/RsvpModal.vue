<script setup>
import { ref } from 'vue';
import GlassModal from '../UI/GlassModal.vue';

const props = defineProps({
    show: { type: Boolean, required: true },
    userCharacters: { type: Array, required: true },
    selectedCharacterId: { type: Number, default: null },
    currentAttendance: { type: Object, default: null },
    csrfToken: { type: String, required: true },
    rsvpRoute: { type: String, required: true },
});
const emit = defineEmits(['close']);

const rsvpCharacterId = ref(props.selectedCharacterId);
const rsvpStatus = ref(props.currentAttendance?.status || 'present');
const rsvpComment = ref(props.currentAttendance?.comment || '');
</script>

<template>
    <GlassModal :show="show" backdrop="bg-black/60" z-index="z-[110]" @close="emit('close')">
        <div class="p-8">
            <div class="flex items-center justify-between mb-8">
                <h2 class="text-xl font-black text-white uppercase tracking-tighter font-headline">Join Raid</h2>
                <button @click="emit('close')" class="text-on-surface-variant hover:text-white transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <form :action="rsvpRoute" method="POST" class="space-y-6">
                <input type="hidden" name="_token" :value="csrfToken">

                <!-- Character select -->
                <div class="space-y-2">
                    <label class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant ml-2">Character</label>
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

                <!-- Status selector -->
                <div class="space-y-2">
                    <label class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant ml-2">Attendance Status</label>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            v-for="status in ['present', 'absent', 'tentative', 'late']"
                            :key="status"
                            @click="rsvpStatus = status"
                            class="px-4 py-2 border rounded-xl text-[10px] font-black uppercase tracking-widest transition-all"
                            :class="rsvpStatus === status
                                ? 'bg-green-600/20 border-green-500 text-green-400'
                                : 'bg-white/5 border-white/10 text-on-surface-variant'"
                        >{{ status }}</button>
                        <input type="hidden" name="status" :value="rsvpStatus">
                    </div>
                </div>

                <!-- Comment -->
                <div class="space-y-2">
                    <label class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant ml-2">Comment (Optional)</label>
                    <input
                        type="text"
                        name="comment"
                        v-model="rsvpComment"
                        placeholder="e.g., Might be 10m late"
                        class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:ring-2 focus:ring-green-500 outline-none"
                    >
                </div>

                <button
                    type="submit"
                    class="w-full bg-green-600 hover:bg-green-500 text-white py-4 rounded-xl font-headline text-xs font-black uppercase tracking-widest transition-all shadow-lg shadow-green-900/20"
                >Confirm Attendance</button>
            </form>
        </div>
    </GlassModal>
</template>
