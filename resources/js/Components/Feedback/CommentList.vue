<script setup>
import { ref, getCurrentInstance } from 'vue';
import ImageUploader from './ImageUploader.vue';
import ImageGallery from './ImageGallery.vue';
import { feedbackApi, routes } from './api.js';

const { proxy } = getCurrentInstance();
const __ = (key, replace = {}) => proxy.__(key, replace);

const props = defineProps({
    comments: { type: Array, required: true },
    postId: { type: Number, required: true },
    isAuthenticated: { type: Boolean, default: false },
});

const emit = defineEmits(['signin-required', 'added', 'removed']);

const localComments = ref([...props.comments]);
const newBody = ref('');
const newImages = ref([]);
const busy = ref(false);
const error = ref(null);

async function submit() {
    if (!props.isAuthenticated) {
        emit('signin-required');
        return;
    }
    if (busy.value || !newBody.value.trim()) return;
    busy.value = true;
    error.value = null;

    try {
        const payload = {
            body: newBody.value,
            images: newImages.value.map((img) => img.path),
        };
        const { data } = await feedbackApi.post(routes.comments(props.postId), payload);
        localComments.value.push({ ...data, can_delete: true });
        newBody.value = '';
        newImages.value = [];
        emit('added', data);
    } catch (e) {
        error.value = e.response?.data?.errors?.body?.[0] || __('Failed to post comment.');
    } finally {
        busy.value = false;
    }
}

async function remove(commentId) {
    if (!confirm(__('Delete this comment?'))) return;
    try {
        await feedbackApi.delete(routes.deleteComment(commentId));
        localComments.value = localComments.value.filter((c) => c.id !== commentId);
        emit('removed', commentId);
    } catch (e) {
        alert(__('Failed to delete comment.'));
    }
}

function formatDate(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <h3 class="text-sm font-headline uppercase tracking-wider text-on-surface-variant">
            {{ __('Comments') }} ({{ localComments.length }})
        </h3>

        <div v-if="localComments.length === 0" class="text-sm text-on-surface-variant/60 py-4">
            {{ __('No comments yet. Be the first to share your thoughts.') }}
        </div>

        <div v-for="comment in localComments" :key="comment.id" class="flex gap-3 p-3 rounded-xl bg-surface-container-high/40 border border-white/5">
            <img
                v-if="comment.author?.avatar_url"
                :src="comment.author.avatar_url"
                :alt="comment.author?.name"
                class="h-8 w-8 rounded-full object-cover shrink-0"
            />
            <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-2 mb-1">
                    <div class="flex items-center gap-2 text-xs">
                        <span class="font-semibold text-on-surface">{{ comment.author?.name }}</span>
                        <span v-if="comment.author?.static_name" class="text-on-surface-variant/70">[{{ comment.author.static_name }}]</span>
                        <span class="text-on-surface-variant/60">· {{ formatDate(comment.created_at) }}</span>
                    </div>
                    <button
                        v-if="comment.can_delete"
                        @click="remove(comment.id)"
                        class="p-1 rounded hover:bg-white/5 text-on-surface-variant hover:text-error transition"
                        :title="__('Delete comment')"
                    >
                        <span class="material-symbols-outlined text-sm">delete</span>
                    </button>
                </div>
                <p class="text-sm text-on-surface whitespace-pre-wrap break-words">{{ comment.body }}</p>
                <ImageGallery v-if="comment.images?.length" :images="comment.images" thumb-size="sm" class="mt-2" />
            </div>
        </div>

        <form v-if="isAuthenticated" @submit.prevent="submit" class="flex flex-col gap-2 mt-2">
            <textarea
                v-model="newBody"
                rows="3"
                maxlength="3000"
                :placeholder="__('Add a comment…')"
                class="w-full px-3 py-2 rounded-lg bg-surface-container-high/60 border border-white/10 focus:border-primary focus:outline-none text-on-surface resize-none text-sm"
            ></textarea>
            <ImageUploader v-model="newImages" />
            <p v-if="error" class="text-xs text-error">{{ error }}</p>
            <div class="flex justify-end">
                <button
                    type="submit"
                    :disabled="busy || !newBody.trim()"
                    class="px-4 py-2 rounded-lg text-xs font-semibold bg-primary text-on-primary hover:bg-primary/90 disabled:opacity-50 transition"
                >
                    {{ __('Post comment') }}
                </button>
            </div>
        </form>

        <div v-else class="p-4 rounded-xl bg-surface-container-high/40 border border-white/5 text-sm text-on-surface-variant flex items-center justify-between">
            <span>{{ __('Sign in to join the conversation.') }}</span>
            <a
                :href="routes.bnetLogin()"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-primary text-on-primary hover:bg-primary/90 transition"
            >
                {{ __('Sign in with Battle.net') }}
            </a>
        </div>
    </div>
</template>
