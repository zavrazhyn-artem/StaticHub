<script setup>
import { ref } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
import SettingsTabs from './SettingsTabs.vue';
import ToastNotification from '@/Components/UI/ToastNotification.vue';
import ConfirmationModal from '@/Components/UI/ConfirmationModal.vue';

const { __ } = useTranslation();

const props = defineProps({
    staticName:     { type: String,  required: true },
    discord:        { type: Object,  required: true },
    statics:        { type: Array,   default: () => [] },
    transferData:   { type: Array,   default: () => [] },
    discordLinkUrl:   { type: String, required: true },
    discordUnlinkUrl: { type: String, required: true },
    leaveStaticUrl:   { type: String, required: true },
    profileTabUrl:  { type: String, required: true },
    scheduleTabUrl: { type: String, required: true },
    discordTabUrl:  { type: String, required: true },
    logsTabUrl:     { type: String, required: true },
    canManage:      { type: Boolean, default: false },
    ownershipTransferred: { type: Boolean, default: false },
});

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

// Toast
const toastShow = ref(false);
const toastMessage = ref('');
const toastIsError = ref(false);
let toastTimer = null;

const showToast = (message, error = false) => {
    clearTimeout(toastTimer);
    toastMessage.value = message;
    toastIsError.value = error;
    toastShow.value = true;
    toastTimer = setTimeout(() => { toastShow.value = false; }, 3000);
};

// Leave static
const leaveLoading = ref(false);

const leaveStatic = async () => {
    if (!confirm(__('Are you sure you want to leave this static group?'))) return;
    leaveLoading.value = true;
    try {
        const res = await fetch(props.leaveStaticUrl, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        });
        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            throw new Error(data.message || 'Failed');
        }
        window.location.reload();
    } catch (e) {
        showToast(e.message || __('Failed to leave static group'), true);
        leaveLoading.value = false;
    }
};

// Unlink discord
const unlinkLoading = ref(false);

const unlinkDiscord = async () => {
    unlinkLoading.value = true;
    try {
        const res = await fetch(props.discordUnlinkUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        });
        if (!res.ok) throw new Error();
        window.location.reload();
    } catch {
        showToast(__('Failed to unlink Discord'), true);
        unlinkLoading.value = false;
    }
};
</script>

<template>
    <ToastNotification
        :show="toastShow"
        :message="toastMessage"
        :icon="toastIsError ? 'error' : 'check_circle'"
        :icon-class="toastIsError ? 'text-error-neon' : 'text-success-neon'"
    />

    <div class="max-w-4xl mx-auto">
        <div class="mb-8">
            <h1 class="text-4xl font-black text-white uppercase tracking-tighter font-headline">{{ __('Static Settings') }}</h1>
            <p class="text-on-surface-variant font-medium mt-1 uppercase tracking-widest text-xs">{{ staticName }}</p>
        </div>

        <SettingsTabs
            :profile-url="profileTabUrl"
            :schedule-url="scheduleTabUrl"
            :discord-url="discordTabUrl"
            :logs-url="logsTabUrl"
            active-tab="profile"
            :can-manage="canManage"
        />

        <!-- Ownership transferred banner -->
        <div v-if="ownershipTransferred" class="p-4 bg-success-neon/10 border border-success-neon/30 rounded-xl flex items-center gap-3 mb-4">
            <span class="material-symbols-outlined text-success-neon">check_circle</span>
            <p class="text-xs font-bold text-success-neon uppercase tracking-widest">{{ __('Ownership transferred successfully.') }}</p>
        </div>

        <div class="space-y-4">
            <!-- Integrations -->
            <div class="bg-surface-container-low border border-white/5 rounded-xl p-8 shadow-2xl backdrop-blur-sm">
                <div class="flex items-center gap-3 mb-6">
                    <span class="material-symbols-outlined text-primary text-xl">link</span>
                    <h2 class="font-headline text-sm font-bold text-white uppercase tracking-[0.2em]">{{ __('Integrations') }}</h2>
                </div>

                <div class="space-y-4">
                    <!-- Discord -->
                    <div class="flex items-center justify-between p-4 bg-surface-container-highest border border-white/5 rounded-lg transition-all hover:bg-white/5 group">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 flex items-center justify-center rounded-lg bg-[#5865F2]/10 text-[#5865F2] group-hover:bg-[#5865F2] group-hover:text-white transition-all">
                                <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24">
                                    <path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037 19.736 19.736 0 0 0-4.885 1.515.069.069 0 0 0-.032.027C.533 9.048-.32 13.572.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.927 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/>
                                </svg>
                            </div>
                            <div>
                                <div class="font-headline text-[10px] font-bold text-white uppercase tracking-widest">Discord</div>
                                <template v-if="discord.connected">
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span class="text-xs font-bold text-success-neon">{{ discord.username }}</span>
                                        <span class="material-symbols-outlined text-success-neon text-sm">check_circle</span>
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mt-0.5">{{ __('Not Connected') }}</div>
                                </template>
                            </div>
                        </div>

                        <button v-if="discord.connected"
                                @click="unlinkDiscord"
                                :disabled="unlinkLoading"
                                class="bg-red-500/10 hover:bg-red-500 text-red-500 hover:text-white font-headline text-[10px] font-bold uppercase tracking-widest py-2 px-4 rounded-sm transition-all active:scale-95 disabled:opacity-50">
                            {{ unlinkLoading ? __('Unlinking...') : __('Unlink') }}
                        </button>
                        <a v-else
                           :href="discordLinkUrl"
                           class="bg-[#5865F2] hover:bg-[#4752C4] text-white font-headline text-[10px] font-bold uppercase tracking-widest py-2 px-4 rounded-sm transition-all active:scale-95 shadow-[0_0_15px_rgba(88,101,242,0.3)] hover:shadow-[0_0_20px_rgba(88,101,242,0.5)]">
                            {{ __('Link Account') }}
                        </a>
                    </div>
                </div>
            </div>

            <!-- Static Group -->
            <div v-if="statics.length" class="bg-surface-container-low border border-white/5 rounded-xl p-8 shadow-2xl backdrop-blur-sm">
                <div class="flex items-center gap-3 mb-6">
                    <span class="material-symbols-outlined text-primary text-xl">groups</span>
                    <h2 class="font-headline text-sm font-bold text-white uppercase tracking-[0.2em]">{{ __('Static Group') }}</h2>
                </div>

                <p class="text-[10px] text-on-surface-variant font-medium uppercase tracking-wider mb-4">{{ __('Manage your static group membership.') }}</p>

                <div v-for="s in statics" :key="s.id" class="space-y-4 mb-4">
                    <div class="flex items-center justify-between p-4 bg-surface-container-highest border border-white/5 rounded-lg">
                        <div>
                            <div class="font-headline text-[10px] font-bold text-white uppercase tracking-widest">{{ s.name }}</div>
                            <div class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mt-0.5">
                                {{ s.isOwner ? __('Owner') : __('Member') }}
                            </div>
                        </div>

                        <button v-if="!s.isOwner"
                                @click="leaveStatic"
                                :disabled="leaveLoading"
                                class="bg-red-500/10 hover:bg-red-500 text-red-500 hover:text-white font-headline text-[10px] font-bold uppercase tracking-widest py-2 px-4 rounded-sm transition-all active:scale-95 disabled:opacity-50">
                            {{ leaveLoading ? __('Leaving...') : __('Leave') }}
                        </button>
                        <span v-else class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">{{ __('Owner') }}</span>
                    </div>

                    <!-- Transfer ownership -->
                    <template v-if="s.isOwner">
                        <template v-for="td in transferData.filter(t => t.id === s.id)" :key="td.id">
                            <transfer-ownership-select
                                v-if="td.members.length"
                                :static-id="td.id"
                                :static-name="td.name"
                                :transfer-url="td.url"
                                :members="td.members"
                                :csrf-token="csrfToken"
                            />
                        </template>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
