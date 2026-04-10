<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';

/**
 * Spec switcher: always shows the active spec icon.
 * If multiple specs are available, clicking opens a popup to switch.
 */
const props = defineProps({
    availableSpecs: { type: Array, default: () => [] },
    activeSpec:     { type: String, default: null },
    mainSpecName:   { type: String, default: null },
    currentSpec:    { type: Object, default: null },
    size:           { type: String, default: 'sm' },
});

const emit = defineEmits(['select']);

const open = ref(false);
const root = ref(null);

const hasMultiple = () => props.availableSpecs.length > 1;

const toggle = () => {
    if (hasMultiple()) {
        open.value = !open.value;
    }
};

const pick = (specName) => {
    emit('select', specName);
    open.value = false;
};

const onClickOutside = (e) => {
    if (open.value && root.value && !root.value.contains(e.target)) {
        open.value = false;
    }
};

onMounted(() => document.addEventListener('click', onClickOutside, true));
onBeforeUnmount(() => document.removeEventListener('click', onClickOutside, true));

const sizeMap = {
    sm: { icon: 'w-5 h-5', popup: 'w-7 h-7', text: 'text-[7px]' },
    xs: { icon: 'w-4 h-4', popup: 'w-5 h-5', text: 'text-[6px]' },
};
const sz = (key) => (sizeMap[props.size] ?? sizeMap.sm)[key];
</script>

<template>
    <div ref="root" class="relative flex-shrink-0">
        <!-- Active spec icon (always visible) -->
        <button
            type="button"
            @click.stop="toggle"
            class="relative rounded overflow-hidden border transition-all"
            :class="[
                sz('icon'),
                hasMultiple()
                    ? 'cursor-pointer border-primary/40 hover:border-primary'
                    : 'cursor-default border-white/10',
            ]"
            :title="activeSpec ?? ''"
        >
            <img
                v-if="currentSpec?.icon_url"
                :src="currentSpec.icon_url"
                :alt="activeSpec"
                class="w-full h-full object-cover"
            />
            <span v-else class="flex items-center justify-center w-full h-full bg-white/5 text-white/40" :class="sz('text')">
                {{ activeSpec?.charAt(0) ?? '?' }}
            </span>

            <!-- Multi-spec indicator dot -->
            <div v-if="hasMultiple()"
                 class="absolute -bottom-0.5 -right-0.5 w-1.5 h-1.5 rounded-full bg-primary border border-[#0e0e10] z-10">
            </div>
        </button>

        <!-- Popup -->
        <Transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="opacity-0 scale-90"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-75 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-90"
        >
            <div v-if="open"
                 class="absolute top-full left-1/2 -translate-x-1/2 mt-1 z-50 bg-[#1a1a1e] border border-white/10 rounded-lg shadow-xl p-1.5 flex gap-1">
                <button
                    v-for="spec in availableSpecs"
                    :key="spec.name"
                    type="button"
                    @click.stop="pick(spec.name)"
                    class="relative rounded overflow-hidden border transition-all"
                    :class="[
                        sz('popup'),
                        activeSpec === spec.name
                            ? 'border-primary ring-1 ring-primary/50'
                            : 'border-white/10 opacity-50 hover:opacity-100 hover:border-white/30',
                    ]"
                    :title="spec.name + (spec.name === mainSpecName ? ' (main)' : '')"
                >
                    <img
                        v-if="spec.icon_url"
                        :src="spec.icon_url"
                        :alt="spec.name"
                        class="w-full h-full object-cover"
                    />
                    <span v-else class="flex items-center justify-center w-full h-full bg-white/5 text-white/40" :class="sz('text')">
                        {{ spec.name?.charAt(0) }}
                    </span>

                    <!-- Main spec star -->
                    <div v-if="spec.name === mainSpecName"
                         class="absolute -top-0.5 -right-0.5 text-yellow-400 leading-none z-10">
                        <span class="text-[6px]">&#9733;</span>
                    </div>
                </button>
            </div>
        </Transition>
    </div>
</template>
