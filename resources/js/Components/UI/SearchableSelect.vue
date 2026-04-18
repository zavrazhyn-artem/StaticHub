<script setup>
import { ref, computed, nextTick, onBeforeUnmount } from 'vue';

const props = defineProps({
    modelValue:        { type: String, default: '' },
    options:           { type: Array,  default: () => [] },  // [{ id, name }]
    inputName:         { type: String, default: '' },
    placeholder:       { type: String, default: 'Select...' },
    searchPlaceholder: { type: String, default: 'Search...' },
    emptyText:         { type: String, default: 'No results found.' },
    icon:              { type: String, default: 'list' },
    prefix:            { type: String, default: '' },        // e.g. '#' or '@'
    loading:           { type: Boolean, default: false },
    disabled:          { type: Boolean, default: false },
    accentColor:       { type: String, default: '#a78bfa' }, // matches --color-primary
    useSearch:         { type: Boolean, default: true },
    compact:           { type: Boolean, default: false },
    dropUp:            { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const search      = ref('');
const open        = ref(false);
const searchRef   = ref(null);
const triggerRef  = ref(null);
const dropdownStyle = ref({});

const selectedLabel = computed(() => {
    const found = props.options.find(o => o.id === props.modelValue);
    return found ? `${props.prefix}${found.name}` : '';
});

const filtered = computed(() => {
    if (!search.value) return props.options;
    const q = search.value.toLowerCase();
    return props.options.filter(o => o.name.toLowerCase().includes(q));
});

function updateDropdownPosition() {
    if (!triggerRef.value) return;
    const rect = triggerRef.value.getBoundingClientRect();
    const style = {
        position: 'fixed',
        left: `${rect.left}px`,
        width: `${rect.width}px`,
    };
    if (props.dropUp) {
        style.bottom = `${window.innerHeight - rect.top + 8}px`;
    } else {
        style.top = `${rect.bottom + 8}px`;
    }
    dropdownStyle.value = style;
}

const toggle = () => {
    if (props.disabled || props.loading) return;
    open.value = !open.value;
    if (open.value) {
        updateDropdownPosition();
        if (props.useSearch) {
            search.value = '';
            nextTick(() => searchRef.value?.focus());
        }
    }
};

const select = (option) => {
    emit('update:modelValue', option.id);
    open.value = false;
    search.value = '';
};

const clear = (e) => {
    e.stopPropagation();
    emit('update:modelValue', '');
};

const close = () => { open.value = false; };

function onScroll() {
    if (open.value) updateDropdownPosition();
}

window.addEventListener('scroll', onScroll, true);
onBeforeUnmount(() => window.removeEventListener('scroll', onScroll, true));

defineExpose({ close });
</script>

<template>
    <div class="relative">
        <!-- Trigger -->
        <div
            ref="triggerRef"
            @click="toggle"
            class="relative w-full bg-surface-container-highest border border-white/5 font-headline font-bold tracking-widest transition-all flex items-center justify-between"
            :class="[
                compact ? 'rounded pl-6 pr-5 py-1 text-4xs' : 'rounded-lg pl-10 pr-8 py-3 text-sm min-h-12',
                loading || disabled
                    ? 'opacity-50 cursor-not-allowed'
                    : 'cursor-pointer hover:border-white/20',
                open ? 'ring-2 border-transparent' : '',
            ]"
            :style="open ? `--tw-ring-color: ${accentColor}33; box-shadow: 0 0 0 2px ${accentColor}55;` : ''"
        >
            <!-- Leading icon -->
            <span :class="['absolute top-1/2 -translate-y-1/2 pointer-events-none', compact ? 'left-1.5' : 'left-3']">
                <span
                    v-if="loading"
                    :class="['material-symbols-outlined animate-spin', compact ? 'text-xs' : 'text-lg']"
                    :style="`color: ${accentColor}`"
                >sync</span>
                <span
                    v-else
                    :class="['material-symbols-outlined transition-colors', compact ? 'text-xs' : 'text-lg', open ? '' : 'text-on-surface-variant']"
                    :style="open ? `color: ${accentColor}` : ''"
                >{{ icon }}</span>
            </span>

            <!-- Label -->
            <span v-if="selectedLabel" class="truncate text-white uppercase">{{ selectedLabel }}</span>
            <span v-else-if="loading" :class="['truncate text-on-surface-variant/60 italic', compact ? '' : 'text-xs']">{{ $slots.loading ? '' : placeholder }}</span>
            <span v-else :class="['truncate text-on-surface-variant/50 italic font-normal', compact ? '' : 'text-xs']">{{ placeholder }}</span>

            <!-- Trailing: clear OR chevron -->
            <span :class="['absolute top-1/2 -translate-y-1/2 flex items-center', compact ? 'right-1.5' : 'right-3']">
                <button
                    v-if="modelValue && !loading && !disabled"
                    type="button"
                    @click="clear"
                    :class="['material-symbols-outlined text-on-surface-variant hover:text-white transition-colors', compact ? 'text-2xs' : 'text-sm']"
                >close</button>
                <span
                    v-else
                    :class="['material-symbols-outlined text-on-surface-variant transition-transform', compact ? 'text-xs' : 'text-base', open ? 'rotate-180' : '']"
                >expand_more</span>
            </span>
        </div>

        <input v-if="inputName" type="hidden" :name="inputName" :value="modelValue">

        <!-- Teleported dropdown — escapes any stacking context -->
        <Teleport to="body">
            <!-- Backdrop -->
            <div v-if="open" class="fixed inset-0 z-[9998]" @click="close" />

            <Transition
                enter-active-class="transition ease-out duration-100"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition ease-in duration-75"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div
                    v-if="open"
                    class="z-[9999] bg-surface-container-high border border-white/10 rounded-xl shadow-2xl overflow-hidden backdrop-blur-xl"
                    :style="dropdownStyle"
                >
                    <!-- Search -->
                    <div v-if="useSearch" class="p-2 border-b border-white/5 sticky top-0 bg-surface-container-high z-10">
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-sm text-on-surface-variant">search</span>
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
                    <div class="max-h-60 overflow-y-auto custom-scrollbar p-1">
                        <div v-if="filtered.length === 0" class="py-4 text-center text-xs text-on-surface-variant">
                            {{ emptyText }}
                        </div>
                        <button
                            v-for="option in filtered"
                            :key="option.id"
                            type="button"
                            class="w-full text-left px-3 py-2 rounded-md text-xs transition-colors flex items-center justify-between gap-2"
                            :class="modelValue === option.id
                                ? 'font-bold'
                                : 'text-on-surface-variant hover:bg-white/5 hover:text-white'"
                            :style="modelValue === option.id ? `background: ${accentColor}22; color: ${accentColor}` : ''"
                            @click="select(option)"
                        >
                            <span class="truncate">{{ prefix }}{{ option.name }}</span>
                            <span v-if="modelValue === option.id" class="material-symbols-outlined text-sm shrink-0">check</span>
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 2px; }
.custom-scrollbar::-webkit-scrollbar-track { background: rgba(255,255,255,.05); }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,.1); border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,.2); }
</style>
