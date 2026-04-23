<script setup>
import { ref, watch, getCurrentInstance } from 'vue';
import GlassModal from '../UI/GlassModal.vue';
import ImageUploader from './ImageUploader.vue';
import { feedbackApi, routes, TAG_META } from './api.js';

const { proxy } = getCurrentInstance();
const __ = (key, replace = {}) => proxy.__(key, replace);

const props = defineProps({
    open: { type: Boolean, default: false },
    initialTitle: { type: String, default: '' },
    initialBody: { type: String, default: '' },
    initialTag: { type: String, default: 'general' },
    initialImages: { type: Array, default: () => [] },
    postId: { type: Number, default: null },
});

const emit = defineEmits(['close', 'created', 'updated']);

const title = ref(props.initialTitle);
const body = ref(props.initialBody);
const tag = ref(props.initialTag);
const images = ref([...props.initialImages]);
const errors = ref({});
const busy = ref(false);

const tagKeys = Object.keys(TAG_META);

watch(() => props.open, (v) => {
    if (v) {
        title.value = props.initialTitle;
        body.value = props.initialBody;
        tag.value = props.initialTag;
        images.value = [...props.initialImages];
        errors.value = {};
    }
});

async function submit() {
    if (busy.value) return;
    busy.value = true;
    errors.value = {};

    try {
        const payload = {
            title: title.value,
            body: body.value,
            tag: tag.value,
            images: images.value.map((img) => img.path),
        };
        if (props.postId) {
            await feedbackApi.patch(routes.post(props.postId), payload);
            emit('updated', { id: props.postId, ...payload, images: images.value });
        } else {
            const { data } = await feedbackApi.post(routes.createPost(), payload);
            emit('created', data);
            window.location.href = data.redirect;
        }
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = e.response.data?.errors || {};
        } else {
            errors.value = { general: [__('Something went wrong. Try again.')] };
        }
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <GlassModal :show="open" max-width="max-w-xl" @close="emit('close')">
        <div class="p-6 flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-headline uppercase tracking-wider text-on-surface">
                    {{ postId ? __('Edit suggestion') : __('New suggestion') }}
                </h2>
                <button type="button" @click="emit('close')" class="p-1 rounded-lg hover:bg-white/5 text-on-surface-variant">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>

            <form @submit.prevent="submit" class="flex flex-col gap-4">
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">{{ __('Title') }}</label>
                    <input
                        v-model="title"
                        type="text"
                        required
                        maxlength="200"
                        class="w-full px-3 py-2 rounded-lg bg-surface-container-high/60 border border-white/10 focus:border-primary focus:outline-none text-on-surface"
                        :placeholder="__('Short, punchy headline')"
                    />
                    <p v-if="errors.title" class="text-xs text-error">{{ errors.title[0] }}</p>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">{{ __('Category') }}</label>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="k in tagKeys"
                            :key="k"
                            type="button"
                            @click="tag = k"
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full border text-3xs font-semibold uppercase tracking-wider transition"
                            :class="tag === k
                                ? TAG_META[k].classes + ' ring-2 ring-primary'
                                : 'bg-white/5 text-on-surface-variant border-white/10 hover:border-primary/40'"
                        >
                            <span class="material-symbols-outlined text-sm leading-none">{{ TAG_META[k].icon }}</span>
                            {{ __(TAG_META[k].label) }}
                        </button>
                    </div>
                    <p v-if="errors.tag" class="text-xs text-error">{{ errors.tag[0] }}</p>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">
                        {{ __('Details') }} <span class="text-on-surface-variant/60 normal-case">{{ __('(optional)') }}</span>
                    </label>
                    <textarea
                        v-model="body"
                        rows="6"
                        maxlength="5000"
                        class="w-full px-3 py-2 rounded-lg bg-surface-container-high/60 border border-white/10 focus:border-primary focus:outline-none text-on-surface resize-none"
                        :placeholder="__('Explain the problem and what you\'d want to see')"
                    ></textarea>
                    <p v-if="errors.body" class="text-xs text-error">{{ errors.body[0] }}</p>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">
                        {{ __('Screenshots') }} <span class="text-on-surface-variant/60 normal-case">{{ __('(optional)') }}</span>
                    </label>
                    <ImageUploader v-model="images" />
                    <p v-if="errors.images" class="text-xs text-error">{{ errors.images[0] }}</p>
                </div>

                <p v-if="errors.general" class="text-xs text-error">{{ errors.general[0] }}</p>

                <div class="flex justify-end gap-2 pt-2">
                    <button
                        type="button"
                        @click="emit('close')"
                        class="px-4 py-2 rounded-lg text-sm font-semibold text-on-surface-variant hover:bg-white/5 transition"
                    >
                        {{ __('Cancel') }}
                    </button>
                    <button
                        type="submit"
                        :disabled="busy || !title.trim()"
                        class="px-4 py-2 rounded-lg text-sm font-semibold bg-primary text-on-primary hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed transition"
                    >
                        {{ postId ? __('Save changes') : __('Submit suggestion') }}
                    </button>
                </div>
            </form>
        </div>
    </GlassModal>
</template>
