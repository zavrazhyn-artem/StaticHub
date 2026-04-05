<script setup>
import { ref, computed, nextTick, getCurrentInstance } from 'vue';

const { proxy } = getCurrentInstance();
const __ = (key, replace = {}) => proxy.__(key, replace);

const props = defineProps({
    staticId:    { type: Number, required: true },
    staticName:  { type: String, required: true },
    transferUrl: { type: String, required: true },
    members:     { type: Array,  default: () => [] },
    csrfToken:   { type: String, required: true },
});

// members: [{ id, name, character: { name, playable_class, avatar_url } | null }]

const classColors = {
    'Death Knight': '#C41F3B', 'Demon Hunter': '#A330C9',
    'Druid':        '#FF7C0A', 'Evoker':       '#33937F',
    'Hunter':       '#ABD473', 'Mage':         '#3FC7EB',
    'Monk':         '#00FF98', 'Paladin':       '#F48CBA',
    'Priest':       '#FFFFFF', 'Rogue':         '#FFF468',
    'Shaman':       '#0070DD', 'Warlock':       '#8788EE',
    'Warrior':      '#C69B6D',
};

const getClassColor = (cls) => classColors[cls] ?? '#9ca3af';

const open          = ref(false);
const search        = ref('');
const searchRef     = ref(null);
const selectedId    = ref('');
const formRef       = ref(null);

const selectedMember = computed(() =>
    props.members.find(m => String(m.id) === String(selectedId.value)) ?? null
);

const filtered = computed(() => {
    if (!search.value) return props.members;
    const q = search.value.toLowerCase();
    return props.members.filter(m =>
        m.character?.name?.toLowerCase().includes(q) ||
        m.name?.toLowerCase().includes(q)
    );
});

const toggle = () => {
    open.value = !open.value;
    if (open.value) {
        search.value = '';
        nextTick(() => searchRef.value?.focus());
    }
};

const select = (member) => {
    selectedId.value = String(member.id);
    open.value = false;
};

const close = () => { open.value = false; };

const submit = () => {
    if (!selectedId.value) return;
    const msg = __('Transfer ownership of :name? You will become an officer.', { name: props.staticName });
    if (!confirm(msg)) return;
    formRef.value?.submit();
};
</script>

<template>
    <!-- Hidden form that does the actual POST -->
    <form ref="formRef" method="POST" :action="transferUrl" class="hidden">
        <input type="hidden" name="_token" :value="csrfToken">
        <input type="hidden" name="new_owner_id" :value="selectedId">
    </form>

    <div class="p-4 bg-surface-container-lowest border border-amber-500/20 rounded-lg">
        <!-- Header -->
        <div class="mb-3">
            <div class="font-headline text-[10px] font-bold text-amber-400 uppercase tracking-widest flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">swap_horiz</span>
                {{ __('Transfer Ownership') }}
            </div>
            <p class="text-[9px] text-gray-500 font-bold uppercase tracking-widest mt-1">
                {{ __('You will become an officer after transfer.') }}
            </p>
        </div>

        <!-- Custom select + button -->
        <div class="flex gap-3">
            <!-- Dropdown wrapper -->
            <div class="relative flex-1">
                <!-- Backdrop -->
                <div v-if="open" class="fixed inset-0 z-40" @click="close" />

                <!-- Trigger -->
                <div
                    @click="toggle"
                    class="relative z-50 w-full bg-surface-container-highest border border-white/10 rounded-lg px-3 py-2 cursor-pointer hover:border-amber-500/40 transition-all flex items-center gap-3 min-h-[40px]"
                    :class="open ? 'ring-2 ring-amber-500/30 border-transparent' : ''"
                >
                    <!-- Selected state -->
                    <template v-if="selectedMember">
                        <img v-if="selectedMember.character?.avatar_url"
                             :src="selectedMember.character.avatar_url"
                             class="w-6 h-6 rounded border border-white/10 shrink-0"
                             :alt="selectedMember.character.name">
                        <div v-else class="w-6 h-6 rounded bg-white/5 border border-white/10 shrink-0 flex items-center justify-center">
                            <span class="material-symbols-outlined text-gray-600 text-[10px]">person</span>
                        </div>
                        <div class="flex items-center gap-1.5 min-w-0">
                            <span class="font-headline text-xs font-bold truncate"
                                  :style="{ color: getClassColor(selectedMember.character?.playable_class) }">
                                {{ selectedMember.character?.name || selectedMember.name }}
                            </span>
                            <span v-if="selectedMember.character" class="text-[9px] text-gray-500 font-bold truncate">
                                ({{ selectedMember.name }})
                            </span>
                        </div>
                    </template>
                    <!-- Placeholder -->
                    <span v-else class="text-[10px] text-on-surface-variant/50 italic font-bold tracking-widest">
                        {{ __('Select new owner...') }}
                    </span>

                    <!-- Chevron -->
                    <span class="material-symbols-outlined text-[16px] text-on-surface-variant transition-transform ml-auto shrink-0"
                          :class="open ? 'rotate-180' : ''">
                        expand_more
                    </span>
                </div>

                <!-- Dropdown -->
                <Transition
                    enter-active-class="transition ease-out duration-100"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                    leave-active-class="transition ease-in duration-75"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                >
                    <div v-if="open"
                         class="absolute z-[60] left-0 right-0 mt-2 bg-surface-container-high border border-white/10 rounded-xl shadow-2xl overflow-hidden backdrop-blur-xl">
                        <!-- Search -->
                        <div class="p-2 border-b border-white/5 sticky top-0 bg-surface-container-high z-10">
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-[14px] text-on-surface-variant">search</span>
                                <input
                                    ref="searchRef"
                                    v-model="search"
                                    type="text"
                                    :placeholder="__('Search...')"
                                    class="w-full bg-surface-container/50 border border-white/5 rounded-lg pl-8 pr-3 py-1.5 text-xs text-white focus:ring-1 focus:ring-amber-500/50 outline-none transition-all"
                                    @click.stop
                                />
                            </div>
                        </div>

                        <!-- Options -->
                        <div class="max-h-56 overflow-y-auto custom-scrollbar p-1">
                            <div v-if="filtered.length === 0"
                                 class="py-4 text-center text-xs text-on-surface-variant">
                                {{ __('No members found.') }}
                            </div>
                            <button
                                v-for="member in filtered"
                                :key="member.id"
                                type="button"
                                class="w-full text-left px-3 py-2 rounded-lg transition-colors flex items-center gap-3 group/opt"
                                :class="String(selectedId) === String(member.id)
                                    ? 'bg-amber-500/15 border border-amber-500/30'
                                    : 'hover:bg-white/5 border border-transparent'"
                                @click="select(member)"
                            >
                                <!-- Avatar -->
                                <img v-if="member.character?.avatar_url"
                                     :src="member.character.avatar_url"
                                     class="w-8 h-8 rounded-lg border border-white/10 shrink-0"
                                     :alt="member.character.name">
                                <div v-else class="w-8 h-8 rounded-lg bg-white/5 border border-white/10 shrink-0 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-gray-600 text-sm">person</span>
                                </div>

                                <!-- Names -->
                                <div class="min-w-0 flex-1">
                                    <div class="font-headline text-xs font-bold truncate"
                                         :style="{ color: getClassColor(member.character?.playable_class) }">
                                        {{ member.character?.name || member.name }}
                                    </div>
                                    <div v-if="member.character" class="text-[9px] text-gray-500 font-bold uppercase tracking-widest truncate">
                                        {{ member.name }}
                                    </div>
                                </div>

                                <!-- Check -->
                                <span v-if="String(selectedId) === String(member.id)"
                                      class="material-symbols-outlined text-amber-400 text-[16px] shrink-0">
                                    check
                                </span>
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>

            <!-- Transfer button -->
            <button
                type="button"
                :disabled="!selectedId"
                @click="submit"
                class="bg-amber-500/10 hover:bg-amber-500 text-amber-400 hover:text-white font-headline text-[10px] font-bold uppercase tracking-widest py-2 px-4 rounded-lg transition-all active:scale-95 whitespace-nowrap disabled:opacity-40 disabled:cursor-not-allowed"
            >
                {{ __('Transfer') }}
            </button>
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 2px; }
.custom-scrollbar::-webkit-scrollbar-track { background: rgba(255,255,255,.05); }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,.1); border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,.2); }
</style>
