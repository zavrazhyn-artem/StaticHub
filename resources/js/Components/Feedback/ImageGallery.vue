<script setup>
import { ref, computed, onBeforeUnmount, getCurrentInstance } from 'vue';

const { proxy } = getCurrentInstance();
const __ = (key, replace = {}) => proxy.__(key, replace);

const props = defineProps({
    images: { type: Array, default: () => [] },
    thumbSize: { type: String, default: 'md' },
});

const lightboxIndex = ref(-1);
const open = computed(() => lightboxIndex.value >= 0);
const active = computed(() => (open.value ? props.images[lightboxIndex.value] : null));

function openAt(i) {
    lightboxIndex.value = i;
    document.addEventListener('keydown', onKey);
    document.body.style.overflow = 'hidden';
}
function close() {
    lightboxIndex.value = -1;
    document.removeEventListener('keydown', onKey);
    document.body.style.overflow = '';
}
function next() {
    if (lightboxIndex.value < props.images.length - 1) lightboxIndex.value++;
}
function prev() {
    if (lightboxIndex.value > 0) lightboxIndex.value--;
}
function onKey(e) {
    if (e.key === 'Escape') close();
    else if (e.key === 'ArrowRight') next();
    else if (e.key === 'ArrowLeft') prev();
}

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKey);
    document.body.style.overflow = '';
});

const thumbClass = computed(() => ({
    sm: 'h-16 w-16',
    md: 'h-24 w-24',
    lg: 'h-32 w-32',
}[props.thumbSize] || 'h-24 w-24'));
</script>

<template>
    <div v-if="images.length > 0">
        <div class="flex flex-wrap gap-2">
            <button
                v-for="(img, i) in images"
                :key="img.path || i"
                type="button"
                @click="openAt(i)"
                class="rounded-lg overflow-hidden border border-white/10 bg-surface-container-high/40 hover:ring-2 hover:ring-primary/40 transition"
                :class="thumbClass"
            >
                <img :src="img.url" alt="" class="h-full w-full object-cover" />
            </button>
        </div>

        <Teleport to="body">
            <div
                v-if="open"
                @click.self="close"
                class="fixed inset-0 z-[200] flex items-center justify-center bg-black/85 backdrop-blur-sm p-4"
            >
                <button
                    type="button"
                    @click="close"
                    class="absolute top-4 right-4 h-10 w-10 rounded-full bg-black/50 text-white hover:bg-error transition flex items-center justify-center"
                    :title="__('Close')"
                >
                    <span class="material-symbols-outlined">close</span>
                </button>

                <button
                    v-if="lightboxIndex > 0"
                    type="button"
                    @click.stop="prev"
                    class="absolute left-4 top-1/2 -translate-y-1/2 h-10 w-10 rounded-full bg-black/50 text-white hover:bg-primary transition flex items-center justify-center"
                    :title="__('Previous')"
                >
                    <span class="material-symbols-outlined">chevron_left</span>
                </button>

                <button
                    v-if="lightboxIndex < images.length - 1"
                    type="button"
                    @click.stop="next"
                    class="absolute right-4 top-1/2 -translate-y-1/2 h-10 w-10 rounded-full bg-black/50 text-white hover:bg-primary transition flex items-center justify-center"
                    :title="__('Next')"
                >
                    <span class="material-symbols-outlined">chevron_right</span>
                </button>

                <img
                    v-if="active"
                    :src="active.url"
                    alt=""
                    class="max-h-full max-w-full object-contain rounded-lg shadow-2xl"
                    @click.stop
                />

                <div class="absolute bottom-4 left-1/2 -translate-x-1/2 text-xs text-white/80 bg-black/50 px-3 py-1 rounded-full">
                    {{ lightboxIndex + 1 }} / {{ images.length }}
                </div>
            </div>
        </Teleport>
    </div>
</template>
