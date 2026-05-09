<script setup>
import { ref, computed, nextTick, onBeforeUnmount } from 'vue';

/**
 * Compact glass-morphism dropdown for short lists where SelectUserWithMain is
 * overkill (no avatars, no search). Used by SlotItemPicker for the track and
 * level selectors.
 *
 * Options: [{ value, label, color? }] where `color` is an optional Tailwind
 * text color class applied to the rendered label so callers can color-code
 * the selected value (e.g. Myth track in orange).
 *
 * The dropdown panel is teleported to <body> and positioned with fixed
 * coords from the button's rect — otherwise modal containers with
 * overflow:hidden clip the panel (which is the entire reason a select
 * inside GlassModal would disappear past the modal's bottom edge).
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
const panelStyle = ref({});

const selected = computed(() => props.options.find(o => String(o.value) === String(props.modelValue)) ?? null);

// Re-anchor the panel to the button on every open and on resize/scroll
// while open. We pick the upward direction when there isn't enough
// headroom below — keeps the panel visible inside crowded modals.
const reposition = () => {
    if (!buttonRef.value) return;
    const r = buttonRef.value.getBoundingClientRect();
    const panelHeight = Math.min(props.options.length * 32 + 8, 320);
    const spaceBelow = window.innerHeight - r.bottom;
    const goUp = spaceBelow < panelHeight + 8 && r.top > panelHeight + 8;
    panelStyle.value = {
        position: 'fixed',
        left: `${r.left}px`,
        top: goUp ? 'auto' : `${r.bottom + 4}px`,
        bottom: goUp ? `${window.innerHeight - r.top + 4}px` : 'auto',
        minWidth: `${r.width}px`,
    };
};

const onWindow = () => { if (open.value) reposition(); };

const toggle = () => {
    open.value = !open.value;
    if (open.value) {
        nextTick(() => {
            reposition();
            window.addEventListener('resize', onWindow, { passive: true });
            window.addEventListener('scroll', onWindow, { passive: true, capture: true });
        });
    } else {
        window.removeEventListener('resize', onWindow);
        window.removeEventListener('scroll', onWindow, { capture: true });
    }
};
const close = () => {
    open.value = false;
    window.removeEventListener('resize', onWindow);
    window.removeEventListener('scroll', onWindow, { capture: true });
};

const select = (option) => {
    emit('update:modelValue', option.value);
    close();
};

onBeforeUnmount(() => {
    window.removeEventListener('resize', onWindow);
    window.removeEventListener('scroll', onWindow, { capture: true });
});
</script>

<template>
    <div class="relative inline-block">
        <button
            ref="buttonRef"
            type="button"
            @click="toggle"
            class="relative bg-surface-container-highest border border-white/10 rounded-lg px-3 py-1.5 text-xs font-bold flex items-center gap-2 hover:border-white/20 transition"
            :class="open ? 'ring-2' : ''"
            :style="[{ minWidth }, open ? `--tw-ring-color: ${accentColor}55` : '']"
        >
            <span :class="selected?.color ?? 'text-white'">
                {{ selected?.label ?? placeholder }}
            </span>
            <span class="material-symbols-outlined text-base text-on-surface-variant transition-transform ml-auto"
                  :class="open ? 'rotate-180' : ''">expand_more</span>
        </button>

        <Teleport to="body">
            <div v-if="open" class="fixed inset-0 z-[2000]" @click="close" />
            <Transition
                enter-active-class="transition ease-out duration-100"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition ease-in duration-75"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div v-if="open"
                     class="z-[2001] bg-surface-container-high border border-white/10 rounded-lg shadow-2xl overflow-hidden backdrop-blur-xl py-1 max-h-80 overflow-y-auto"
                     :style="panelStyle">
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
        </Teleport>
    </div>
</template>
