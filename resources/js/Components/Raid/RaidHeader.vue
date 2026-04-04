<script setup>
import { computed } from 'vue';
import { useTimeFormatter } from '../../composables/useTimeFormatter.js';

const props = defineProps({
    event: { type: Object, required: true },
    raidStatus: { type: Array, required: true },
    currentAttendance: { type: Object, default: null },
    joinedCharacter: { type: Object, default: null },
    joinedRoleLabel: { type: String, default: '' },
    authUserId: { type: Number, required: true },
    csrfToken: { type: String, required: true },
    routes: { type: Object, required: true },
});
const emit = defineEmits(['rsvp', 'edit', 'delete']);

const { formatDate, formatTime } = useTimeFormatter();
const isOwner = computed(() => props.authUserId === props.event.static?.owner_id);
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center gap-3">
            <a :href="routes.index" class="text-on-surface-variant hover:text-primary transition-colors">
                <span class="material-symbols-outlined text-xl">arrow_back</span>
            </a>
            <h1 class="text-3xl font-black text-white uppercase tracking-tighter font-headline leading-none">
                {{ event.static?.name || 'Raid Event' }}
            </h1>
        </div>

        <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-4 w-full">

            <div class="flex flex-wrap items-center gap-3">
                <div class="bg-surface-container-high border border-white/10 rounded-xl px-4 h-10 glassmorphism backdrop-blur-md shadow-2xl flex items-center gap-4">
                    <div class="flex items-center gap-2 text-white font-headline text-[11px] font-black uppercase tracking-widest leading-none">
                        <span class="material-symbols-outlined text-sm text-primary">calendar_today</span>
                        <span>{{ formatDate(event.start_time) }}</span>
                    </div>
                    <div class="flex items-center gap-2 text-white font-headline text-[11px] font-black uppercase tracking-widest leading-none border-l border-white/10 pl-4">
                        <span class="material-symbols-outlined text-sm text-primary">schedule</span>
                        <span>{{ formatTime(event.start_time) }}</span>
                        <span class="text-on-surface-variant">-</span>
                        <span>{{ formatTime(event.end_time) }}</span>
                    </div>
                </div>

                <div class="bg-surface-container-high border border-white/10 rounded-xl px-4 h-10 glassmorphism backdrop-blur-md shadow-2xl flex items-center gap-4">
                    <div v-for="status in raidStatus" :key="status.label" class="flex items-center gap-1.5">
                        <img :src="'/images/roles/' + status.label" class="w-4 h-4 opacity-90" :alt="status.label">
                        <span class="text-[11px] font-black tracking-tight leading-none" :class="status.color">
                            {{ status.count }}/{{ status.limit }}
                        </span>
                    </div>
                </div>
            </div>

            <div
                v-if="currentAttendance && currentAttendance.status !== 'pending' && joinedCharacter"
                class="flex-1 flex items-center justify-center px-4 h-10 bg-surface-container-high border border-white/10 rounded-xl glassmorphism backdrop-blur-md shadow-2xl"
            >
                <div class="text-white font-headline text-[11px] font-black uppercase tracking-[0.15em] leading-none opacity-80 truncate">
                    Joined as <span class="text-primary">{{ joinedCharacter?.name }}</span>
                    &bull;
                    <span class="text-primary">{{ joinedRoleLabel }}</span>
                </div>
            </div>

            <div class="flex items-center gap-2 h-10 shrink-0">

                <button
                    v-if="!currentAttendance || currentAttendance.status === 'pending'"
                    @click="emit('rsvp')"
                    class="h-10 px-6 bg-success-neon text-black hover:bg-[#00ffb3] hover:shadow-[0_0_15px_rgba(0,255,152,0.3)] rounded-xl flex items-center justify-center gap-2 transition-all duration-300 group shadow-lg"
                    title="Join Raid"
                >
                    <span class="material-symbols-outlined text-[18px] font-bold group-hover:scale-110 transition-transform">person_add</span>
                    <span class="font-headline text-xs font-black uppercase tracking-widest mt-[1px]">Join</span>
                </button>

                <button
                    v-else
                    @click="emit('rsvp')"
                    class="w-10 h-10 bg-white/10 hover:bg-white/20 text-white rounded-xl transition-all border border-white/10 flex items-center justify-center shadow-lg"
                    title="Change RSVP"
                >
                    <span class="material-symbols-outlined text-xl">edit_calendar</span>
                </button>

                <button
                    v-if="isOwner"
                    @click="emit('edit')"
                    class="w-10 h-10 bg-yellow-600/20 hover:bg-yellow-600/30 text-yellow-500 rounded-xl transition-all border border-yellow-600/30 flex items-center justify-center shadow-lg"
                    title="Edit Event"
                >
                    <span class="material-symbols-outlined text-xl">edit</span>
                </button>

                <button
                    v-if="isOwner"
                    @click="emit('delete')"
                    class="w-10 h-10 bg-red-600/20 hover:bg-red-600/30 text-red-500 rounded-xl transition-all border border-red-600/30 flex items-center justify-center shadow-lg"
                    title="Delete Event"
                >
                    <span class="material-symbols-outlined text-xl">delete</span>
                </button>

                <form v-if="isOwner" :action="routes.announce" method="POST" class="h-full flex items-center">
                    <input type="hidden" name="_token" :value="csrfToken">
                    <button
                        type="submit"
                        class="w-10 h-10 bg-[#5865F2]/20 hover:bg-[#5865F2]/30 text-[#5865F2] rounded-xl transition-all border border-[#5865F2]/30 flex items-center justify-center shadow-lg"
                        title="Discord Announce"
                    >
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M20.317 4.3698a19.7913 19.7913 0 00-4.8851-1.5152.0741.0741 0 00-.0785.0371c-.211.3753-.4447.8648-.6083 1.2495-1.8447-.2758-3.68-.2758-5.4868 0-.1636-.3933-.4058-.8742-.6177-1.2495a.077.077 0 00-.0785-.037 19.7363 19.7363 0 00-4.8852 1.515.0699.0699 0 00-.0321.0277C.5334 9.0458-.319 13.5799.0992 18.0578a.0824.0824 0 00.0312.0561c2.0528 1.5076 4.0413 2.4228 5.9929 3.0294a.0777.0777 0 00.0842-.0276c.4616-.6304.8731-1.2952 1.226-1.9942a.076.076 0 00-.0416-.1057c-.6528-.2476-1.2743-.5495-1.8722-.8923a.077.077 0 01-.0076-.1277c.1258-.0943.2517-.1923.3718-.2914a.0743.0743 0 01.0776-.0105c3.9278 1.7933 8.18 1.7933 12.0614 0a.0739.0739 0 01.0785.0095c.1202.099.246.1971.3728.2924a.077.077 0 01-.0066.1276 12.2986 12.2986 0 01-1.873.8914.0766.0766 0 00-.0407.1067c.3604.698.7719 1.3628 1.225 1.9932a.076.076 0 00.0842.0286c1.961-.6067 3.9495-1.5219 6.0023-3.0294a.077.077 0 00.0313-.0552c.5004-5.177-.8382-9.6739-3.5485-13.6604a.061.061 0 00-.0312-.0286zM8.02 15.3312c-1.1825 0-2.1569-1.0857-2.1569-2.419 0-1.3332.9555-2.4189 2.157-2.4189 1.2108 0 2.1757 1.0952 2.1568 2.419 0 1.3332-.9555 2.4189-2.1569 2.4189zm7.9748 0c-1.1825 0-2.1569-1.0857-2.1569-2.419 0-1.3332.9554-2.4189 2.1569-2.4189 1.2108 0 2.1757 1.0952 2.1568 2.419 0 1.3332-.946 2.4189-2.1568 2.4189z"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<style scoped>
.glassmorphism {
    background: rgba(23, 23, 23, 0.8);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}
</style>
