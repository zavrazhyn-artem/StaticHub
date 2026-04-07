<script setup>
import { ref, computed, nextTick } from 'vue';

const props = defineProps({
    modelValue:        { type: [String, Number], default: '' },
    members:           { type: Array, required: true }, // [{ id, name, character: { name, playable_class, avatar_url } | null }]
    placeholder:       { type: String, default: 'Select a member...' },
    searchPlaceholder: { type: String, default: 'Search...' },
    emptyText:         { type: String, default: 'No members found.' },
    inputName:         { type: String, default: '' },
    accentColor:       { type: String, default: '#a78bfa' },
    disabled:          { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const classColors = {
    'Death Knight': '#C41F3B', 'Demon Hunter': '#A330C9',
    'Druid':        '#FF7C0A', 'Evoker':       '#33937F',
    'Hunter':       '#ABD473', 'Mage':         '#3FC7EB',
    'Monk':         '#00FF98', 'Paladin':      '#F48CBA',
    'Priest':       '#FFFFFF', 'Rogue':        '#FFF468',
    'Shaman':       '#0070DD', 'Warlock':      '#8788EE',
    'Warrior':      '#C69B6D',
};
const getClassColor = (cls) => classColors[cls] ?? '#9ca3af';

const open      = ref(false);
const search    = ref('');
const searchRef = ref(null);

const selectedMember = computed(() =>
    props.members.find(m => String(m.id) === String(props.modelValue)) ?? null
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
    if (props.disabled) return;
    open.value = !open.value;
    if (open.value) {
        search.value = '';
        nextTick(() => searchRef.value?.focus());
    }
};

const select = (member) => {
    emit('update:modelValue', String(member.id));
    open.value = false;
};

const close = () => { open.value = false; };
defineExpose({ close });
</script>

<template>
    <div class="relative">
        <div v-if="open" class="fixed inset-0 z-40" @click="close" />

        <!-- Trigger -->
        <div
            @click="toggle"
            class="relative z-50 w-full bg-surface-container-highest border border-white/10 rounded-lg px-3 py-2 transition-all flex items-center gap-3 min-h-[40px]"
            :class="[
                disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:border-white/20',
                open ? 'ring-2 border-transparent' : '',
            ]"
            :style="open ? `--tw-ring-color: ${accentColor}33; box-shadow: 0 0 0 2px ${accentColor}55;` : ''"
        >
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
            <span v-else class="text-[10px] text-on-surface-variant/50 italic font-bold tracking-widest">
                {{ placeholder }}
            </span>
            <span class="material-symbols-outlined text-[16px] text-on-surface-variant transition-transform ml-auto shrink-0"
                  :class="open ? 'rotate-180' : ''">expand_more</span>
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
                            :placeholder="searchPlaceholder"
                            class="w-full bg-surface-container/50 border border-white/5 rounded-lg pl-8 pr-3 py-1.5 text-xs text-white focus:ring-1 outline-none transition-all"
                            :style="`--tw-ring-color: ${accentColor}88`"
                            @click.stop
                        />
                    </div>
                </div>

                <!-- Options -->
                <div class="max-h-56 overflow-y-auto custom-scrollbar p-1">
                    <div v-if="filtered.length === 0" class="py-4 text-center text-xs text-on-surface-variant">
                        {{ emptyText }}
                    </div>
                    <button
                        v-for="member in filtered"
                        :key="member.id"
                        type="button"
                        class="w-full text-left px-3 py-2 rounded-lg transition-colors flex items-center gap-3"
                        :class="String(modelValue) === String(member.id)
                            ? 'border border-transparent'
                            : 'hover:bg-white/5 border border-transparent'"
                        :style="String(modelValue) === String(member.id) ? `background: ${accentColor}15; border-color: ${accentColor}44;` : ''"
                        @click="select(member)"
                    >
                        <img v-if="member.character?.avatar_url"
                             :src="member.character.avatar_url"
                             class="w-8 h-8 rounded-lg border border-white/10 shrink-0"
                             :alt="member.character.name">
                        <div v-else class="w-8 h-8 rounded-lg bg-white/5 border border-white/10 shrink-0 flex items-center justify-center">
                            <span class="material-symbols-outlined text-gray-600 text-sm">person</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="font-headline text-xs font-bold truncate"
                                 :style="{ color: getClassColor(member.character?.playable_class) }">
                                {{ member.character?.name || member.name }}
                            </div>
                            <div v-if="member.character" class="text-[9px] text-gray-500 font-bold uppercase tracking-widest truncate">
                                {{ member.name }}
                            </div>
                        </div>
                        <span v-if="String(modelValue) === String(member.id)"
                              class="material-symbols-outlined text-[16px] shrink-0"
                              :style="`color: ${accentColor}`">check</span>
                    </button>
                </div>
            </div>
        </Transition>

        <input v-if="inputName" type="hidden" :name="inputName" :value="modelValue">
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 2px; }
.custom-scrollbar::-webkit-scrollbar-track { background: rgba(255,255,255,.05); }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,.1); border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,.2); }
</style>
