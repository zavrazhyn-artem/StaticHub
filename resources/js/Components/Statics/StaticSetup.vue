<script setup>
import { ref } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
import InviteCodeModal from '../UI/InviteCodeModal.vue';

const { __ } = useTranslation();

const props = defineProps({
    realms:    { type: Array, default: () => [] },
    guilds:    { type: Array, default: () => [] },
    storeUrl:  { type: String, required: true },
    importUrl: { type: String, required: true },
});

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const showInviteModal = ref(false);
const inviteCode = ref('');

function handleCreateClick(event) {
    event.preventDefault();
    showInviteModal.value = true;
}

function handleInviteConfirmed(code) {
    inviteCode.value = code;
    showInviteModal.value = false;

    // Submit the form after invite code is confirmed
    setTimeout(() => {
        document.getElementById('create-static-form').submit();
    }, 50);
}
</script>

<template>
    <div class="space-y-8 max-w-xl mx-auto">
        <!-- Create Static -->
        <div class="bg-surface-container-low border border-white/5 rounded-xl p-8">
            <h2 class="text-2xl font-black text-white uppercase tracking-tight font-headline mb-1">{{ __('Create a New Static') }}</h2>
            <p class="text-on-surface-variant text-sm mb-6">{{ __('Manually create a new raiding group.') }}</p>

            <form id="create-static-form" method="POST" :action="storeUrl" class="space-y-6">
                <input type="hidden" name="_token" :value="csrfToken">
                <input type="hidden" name="invite_code" :value="inviteCode">

                <div class="space-y-2">
                    <label for="name" class="block text-3xs font-bold text-on-surface-variant uppercase tracking-widest">{{ __('Static Name') }}</label>
                    <input id="name" name="name" type="text" required
                        class="block w-full px-4 py-3 bg-surface-container-highest border border-white/5 rounded-lg font-headline text-sm font-bold text-white tracking-widest focus:ring-2 focus:ring-primary focus:border-transparent transition-all outline-none">
                </div>

                <div class="space-y-2">
                    <label for="realm_slug" class="block text-3xs font-bold text-on-surface-variant uppercase tracking-widest">{{ __('Server') }}</label>
                    <select id="realm_slug" name="realm_slug" required
                        class="block w-full px-4 py-3 bg-surface-container-highest border border-white/5 rounded-lg font-headline text-sm font-bold text-white tracking-widest focus:ring-2 focus:ring-primary focus:border-transparent transition-all outline-none appearance-none">
                        <option value="">{{ __('Select a Server') }}</option>
                        <option v-for="realm in realms" :key="realm.slug" :value="realm.slug">{{ realm.name }}</option>
                    </select>
                </div>

                <div class="space-y-2">
                    <label for="region" class="block text-3xs font-bold text-on-surface-variant uppercase tracking-widest">{{ __('Region') }}</label>
                    <select id="region" name="region"
                        class="block w-full px-4 py-3 bg-surface-container-highest border border-white/5 rounded-lg font-headline text-sm font-bold text-white tracking-widest focus:ring-2 focus:ring-primary focus:border-transparent transition-all outline-none appearance-none">
                        <option value="eu">{{ __('Europe') }}</option>
                        <option value="us">{{ __('Americas') }}</option>
                        <option value="kr">{{ __('Korea') }}</option>
                        <option value="tw">{{ __('Taiwan') }}</option>
                    </select>
                </div>

                <button type="button" @click="handleCreateClick"
                    class="bg-primary text-on-primary px-8 py-3 rounded-sm font-headline text-xs font-bold uppercase tracking-[0.2em] hover:brightness-110 active:scale-95 transition-all">
                    {{ __('Create Static') }}
                </button>
            </form>
        </div>

        <!-- Import from Battle.net -->
        <div v-if="guilds.length" class="bg-surface-container-low border border-white/5 rounded-xl p-8">
            <h2 class="text-2xl font-black text-white uppercase tracking-tight font-headline mb-1">{{ __('Import from Battle.net') }}</h2>
            <p class="text-on-surface-variant text-sm mb-6">{{ __('We found these guilds on your account. Import them as a Static.') }}</p>

            <div class="space-y-4">
                <div v-for="guild in guilds" :key="guild.name"
                     class="flex items-center justify-between p-4 bg-surface-container-highest border border-white/5 rounded-lg">
                    <div>
                        <p class="text-sm font-bold text-white">{{ guild.name }}</p>
                        <p class="text-xs text-on-surface-variant">{{ guild.realm }}</p>
                    </div>
                    <form method="POST" :action="importUrl">
                        <input type="hidden" name="_token" :value="csrfToken">
                        <input type="hidden" name="name" :value="guild.name">
                        <input type="hidden" name="realm_slug" :value="guild.realm_slug">
                        <input type="hidden" name="realm" :value="guild.realm">
                        <button type="submit"
                            class="border border-white/20 text-on-surface-variant hover:border-primary hover:text-primary px-4 py-2 rounded text-3xs font-bold uppercase tracking-widest transition-all">
                            {{ __('Import Guild') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <InviteCodeModal
            :show="showInviteModal"
            :csrf-token="csrfToken"
            @confirmed="handleInviteConfirmed"
            @close="showInviteModal = false"
        />
    </div>
</template>
