<script setup>
import { ref, computed, nextTick } from 'vue';

/**
 * Compact glass-morphism dropdown for short lists where SelectUserWithMain is
 * overkill (no avatars, no search). Used by SlotItemPicker for the track and
 * level selectors.
 *
 * Options: [{ value, label, color? }] where `color` is an optional Tailwind
 * text color class applied to the rendered label so callers can color-code
 * the selected value (e.g. Myth track in orange).
 */
const props = defineProps({
    modelValue: { type: [String, Number, null], default: null },
    options: { type: Array, required: true },
    placeholder: { type: String, default: '—' },
    accentColor: { type: String, default: '#22d3ee' },
    minWidth: { type: String, default: '90px' },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const buttonRef = ref(null);

const selected = computed(() => props.options.find(o => String(o.value) === String(props.modelValue)) ?? null);

const toggle = () => {
    open.value = !open.value;
};
const close = () => { open.value = false; };

const select = (option) => {
    emit('update:modelValue', option.value);
    open.value = false;
};
</script>

<template>
    <div class="relative inline-block">
        <div v-if="open" class="fixed inset-0 z-40" @click="close" />

        <button
            ref="buttonRef"
            type="button"
            @click="toggle"
            class="relative z-50 bg-surface-container-highest border border-white/10 rounded-lg px-3 py-1.5 text-xs font-bold flex items-center gap-2 hover:border-white/20 transition"
            :class="open ? 'ring-2' : ''"
            :style="[{ minWidth }, open ? `--tw-ring-color: ${accentColor}55` : '']"
        >
            <span :class="selected?.color ?? 'text-white'">
                {{ selected?.label ?? placeholder }}
            </span>
            <span class="material-symbols-outlined text-base text-on-surface-variant transition-transform ml-auto"
                  :class="open ? 'rotate-180' : ''">expand_more</span>
        </button>

        <Transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div v-if="open"
                 class="absolute z-[60] left-0 mt-1 bg-surface-container-high border border-white/10 rounded-lg shadow-2xl overflow-hidden backdrop-blur-xl py-1"
                 :style="`min-width: ${minWidth}`">
                <button
                    v-for="opt in options"
                    :key="opt.value"
                    type="button"
                    class="w-full text-left px-3 py-1.5 text-xs font-bold transition flex items-center gap-2"
                    :class="[
                        opt.color ?? 'text-white',
                        String(modelValue) === String(opt.value)
                            ? 'bg-white/10'
                            : 'hover:bg-white/5'
                    ]"
                    @click="select(opt)"
                >
                    {{ opt.label }}
                    <span v-if="String(modelValue) === String(opt.value)"
                          class="material-symbols-outlined text-sm ml-auto"
                          :style="`color: ${accentColor}`">check</span>
                </button>
            </div>
        </Transition>
    </div>
</template>
