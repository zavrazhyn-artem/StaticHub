<script setup>
import { ref, computed, getCurrentInstance } from 'vue';
import { feedbackApi, routes } from './api.js';

const { proxy } = getCurrentInstance();
const __ = (key, replace = {}) => proxy.__(key, replace);

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    maxFiles: { type: Number, default: 5 },
    maxBytes: { type: Number, default: 5 * 1024 * 1024 },
});
const emit = defineEmits(['update:modelValue']);

const ALLOWED = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

const fileInput = ref(null);
const dragging = ref(false);
const uploading = ref(0);
const errorMessage = ref('');

const images = computed(() => props.modelValue);
const remaining = computed(() => Math.max(0, props.maxFiles - images.value.length));

function openPicker() {
    if (remaining.value === 0) return;
    fileInput.value?.click();
}

async function onFilesSelected(event) {
    const files = Array.from(event.target.files || []);
    event.target.value = '';
    await uploadFiles(files);
}

async function onDrop(event) {
    event.preventDefault();
    dragging.value = false;
    const files = Array.from(event.dataTransfer?.files || []);
    await uploadFiles(files);
}

function onDragOver(event) {
    if (remaining.value > 0) {
        event.preventDefault();
        dragging.value = true;
    }
}

function onDragLeave() {
    dragging.value = false;
}

async function uploadFiles(files) {
    errorMessage.value = '';
    const accepted = [];
    for (const file of files) {
        if (accepted.length + images.value.length >= props.maxFiles) {
            errorMessage.value = __('Max :n images.', { n: props.maxFiles });
            break;
        }
        if (!ALLOWED.includes(file.type)) {
            errorMessage.value = __('Unsupported file type: :name', { name: file.name });
            continue;
        }
        if (file.size > props.maxBytes) {
            errorMessage.value = __('File too large (max 5 MB): :name', { name: file.name });
            continue;
        }
        accepted.push(file);
    }

    const next = [...images.value];
    for (const file of accepted) {
        uploading.value++;
        try {
            const form = new FormData();
            form.append('image', file);
            const { data } = await feedbackApi.post(routes.uploads(), form, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            next.push({ path: data.path, url: data.url });
            emit('update:modelValue', [...next]);
        } catch (e) {
            errorMessage.value = e.response?.data?.message || __('Upload failed.');
        } finally {
            uploading.value--;
        }
    }
}

function removeAt(index) {
    const next = images.value.filter((_, i) => i !== index);
    emit('update:modelValue', next);
}

async function onPaste(event) {
    if (remaining.value === 0) return;
    const items = Array.from(event.clipboardData?.items || []);
    const files = items
        .filter((it) => it.kind === 'file' && ALLOWED.includes(it.type))
        .map((it) => it.getAsFile())
        .filter(Boolean);
    if (files.length > 0) {
        event.preventDefault();
        await uploadFiles(files);
    }
}

defineExpose({ onPaste });
</script>

<template>
    <div>
        <div
            @click="openPicker"
            @dragover="onDragOver"
            @dragleave="onDragLeave"
            @drop="onDrop"
            class="relative flex flex-col items-center justify-center gap-1 px-4 py-6 rounded-xl border-2 border-dashed cursor-pointer transition"
            :class="dragging
                ? 'border-primary bg-primary/10'
                : 'border-white/10 bg-surface-container-high/30 hover:border-primary/40 hover:bg-surface-container-high/60'"
        >
            <span class="material-symbols-outlined text-2xl text-on-surface-variant">
                {{ uploading > 0 ? 'progress_activity' : 'cloud_upload' }}
            </span>
            <div class="text-xs text-on-surface-variant text-center">
                <span v-if="uploading > 0">{{ __('Uploading…') }}</span>
                <span v-else-if="remaining === 0">{{ __('Max :n images reached.', { n: maxFiles }) }}</span>
                <span v-else>
                    {{ __('Drop images, click, or paste') }}
                    <span class="block text-on-surface-variant/60 mt-0.5">
                        {{ __('PNG · JPG · GIF · WEBP — max 5 MB · :n left', { n: remaining }) }}
                    </span>
                </span>
            </div>
            <input
                ref="fileInput"
                type="file"
                accept="image/jpeg,image/png,image/gif,image/webp"
                multiple
                class="hidden"
                @change="onFilesSelected"
            />
        </div>

        <p v-if="errorMessage" class="mt-1.5 text-xs text-error">{{ errorMessage }}</p>

        <div v-if="images.length > 0" class="mt-3 flex flex-wrap gap-2">
            <div
                v-for="(img, i) in images"
                :key="img.path"
                class="relative h-20 w-20 rounded-lg overflow-hidden border border-white/10 bg-surface-container-high/40 group"
            >
                <img :src="img.url" alt="" class="h-full w-full object-cover" />
                <button
                    type="button"
                    @click.stop="removeAt(i)"
                    class="absolute top-1 right-1 h-5 w-5 rounded-full bg-black/70 text-white text-xs flex items-center justify-center hover:bg-error transition opacity-0 group-hover:opacity-100"
                    :title="__('Remove')"
                >
                    <span class="material-symbols-outlined" style="font-size: 14px;">close</span>
                </button>
            </div>
        </div>
    </div>
</template>
