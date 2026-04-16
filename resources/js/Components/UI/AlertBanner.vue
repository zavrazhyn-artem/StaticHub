<script setup>
import { ref, onMounted } from 'vue';

const props = defineProps({
    storageKey:  { type: String, default: null },
    variant:     { type: String, default: 'warning' }, // warning, info, error, success
    icon:        { type: String, default: 'warning' },
    actionLabel: { type: String, default: null },
    actionUrl:   { type: String, default: null },
    dismissible: { type: Boolean, default: true },
});

const emit = defineEmits(['action', 'dismiss']);

const visible = ref(false);

const variantClasses = {
    warning: 'bg-amber-500/10 border-amber-500/30 text-amber-400',
    info:    'bg-primary/10 border-primary/30 text-primary',
    error:   'bg-red-500/10 border-red-500/30 text-red-400',
    success: 'bg-success-neon/10 border-success-neon/30 text-success-neon',
};

const actionVariantClasses = {
    warning: 'bg-amber-500 hover:bg-amber-400 text-black',
    info:    'bg-primary hover:bg-cyan-400 text-black',
    error:   'bg-red-500 hover:bg-red-400 text-white',
    success: 'bg-success-neon hover:bg-green-400 text-black',
};

onMounted(() => {
    if (props.storageKey) {
        visible.value = !localStorage.getItem(`alert_dismissed_${props.storageKey}`);
    } else {
        visible.value = true;
    }
});

const dismiss = () => {
    visible.value = false;
    if (props.storageKey) {
        localStorage.setItem(`alert_dismissed_${props.storageKey}`, '1');
    }
    emit('dismiss');
};

const handleAction = () => {
    if (props.actionUrl) {
        window.location.href = props.actionUrl;
    }
    emit('action');
};
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0 -translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-2"
    >
        <div v-if="visible"
             class="flex items-center justify-center gap-3 px-4 py-2 border rounded-lg backdrop-blur-sm"
             :class="variantClasses[variant]">
            <span class="material-symbols-outlined text-base leading-none">{{ icon }}</span>

            <span class="text-3xs font-bold uppercase tracking-widest leading-none">
                <slot />
            </span>

            <button v-if="actionLabel"
                    @click="handleAction"
                    class="ml-2 text-3xs font-bold uppercase tracking-widest leading-none px-3 py-1.5 rounded-sm transition-all active:scale-95 flex items-center"
                    :class="actionVariantClasses[variant]">
                {{ actionLabel }}
            </button>

            <button v-if="dismissible"
                    @click="dismiss"
                    class="ml-1 opacity-60 hover:opacity-100 transition-opacity flex items-center">
                <span class="material-symbols-outlined text-sm leading-none">close</span>
            </button>
        </div>
    </Transition>
</template>
