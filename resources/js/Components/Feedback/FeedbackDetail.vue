<script setup>
import { ref, computed, getCurrentInstance } from 'vue';
import StatusBadge from './StatusBadge.vue';
import TagBadge from './TagBadge.vue';
import VoteButton from './VoteButton.vue';
import CommentList from './CommentList.vue';
import SubtaskList from './SubtaskList.vue';
import FeedbackForm from './FeedbackForm.vue';
import FeedbackTabs from './FeedbackTabs.vue';
import ImageGallery from './ImageGallery.vue';
import { feedbackApi, routes, STATUS_META, classColor } from './api.js';

const { proxy } = getCurrentInstance();
const __ = (key, replace = {}) => proxy.__(key, replace);

const props = defineProps({
    payload: { type: Object, required: true },
    isAuthenticated: { type: Boolean, default: false },
    canManage: { type: Boolean, default: false },
    isAuthor: { type: Boolean, default: false },
});

const post = ref(props.payload.post);
const comments = ref(props.payload.comments);
const subtasks = ref(props.payload.subtasks);

const showEditForm = ref(false);
const showAuthToast = ref(false);
const busy = ref(false);

const canEdit = computed(() => props.canManage || props.isAuthor);
const authorColor = computed(() => classColor(post.value.author?.playable_class));

function handleSigninRequired() {
    if (props.isAuthenticated) return;
    showAuthToast.value = true;
    setTimeout(() => (showAuthToast.value = false), 3500);
}

async function changeStatus(newStatus) {
    if (!props.canManage || busy.value) return;
    const prev = post.value.status;
    post.value.status = newStatus;
    busy.value = true;

    try {
        await feedbackApi.patch(routes.updateStatus(post.value.id), { status: newStatus });
    } catch (e) {
        post.value.status = prev;
        alert(__('Failed to update status.'));
    } finally {
        busy.value = false;
    }
}

async function destroy() {
    if (!confirm(__('Delete this post? This cannot be undone.'))) return;
    try {
        await feedbackApi.delete(routes.post(post.value.id));
        window.location.href = '/feedback';
    } catch (e) {
        alert(__('Failed to delete post.'));
    }
}

function onUpdated(data) {
    post.value.title = data.title;
    post.value.body = data.body;
    if (data.tag) post.value.tag = data.tag;
    if (Array.isArray(data.images)) post.value.images = data.images;
    showEditForm.value = false;
}

function formatDate(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}
</script>

<template>
    <div class="pb-16">
        <div class="w-full lg:w-[85%] mx-auto">
            <FeedbackTabs active="feedback" />

            <a href="/feedback" class="inline-flex items-center gap-1 text-xs text-on-surface-variant hover:text-on-surface mb-4 transition">
                <span class="material-symbols-outlined text-sm">arrow_back</span>
                {{ __('Back to feedback') }}
            </a>

            <div class="flex gap-4 p-6 rounded-2xl bg-surface-container-high/60 border border-white/5 mb-6">
                <VoteButton
                    :post-id="post.id"
                    :initial-voted="!!post.user_has_voted"
                    :initial-count="post.votes_count"
                    :is-authenticated="isAuthenticated"
                    variant="vertical"
                    @signin-required="handleSigninRequired"
                />

                <div class="flex-1 min-w-0 flex flex-col gap-3">
                    <div class="flex items-start justify-between gap-3">
                        <h1 class="text-xl sm:text-2xl font-headline uppercase tracking-tight text-on-surface">
                            {{ post.title }}
                        </h1>
                        <div class="flex items-center gap-2 shrink-0 mt-1">
                            <TagBadge v-if="post.tag" :tag="post.tag" size="md" />
                            <StatusBadge :status="post.status" size="md" />
                        </div>
                    </div>

                    <p v-if="post.body" class="text-sm text-on-surface whitespace-pre-wrap leading-relaxed">
                        {{ post.body }}
                    </p>

                    <ImageGallery v-if="post.images?.length" :images="post.images" thumb-size="md" />

                    <div class="flex items-center gap-3 text-xs text-on-surface-variant pt-2 border-t border-white/5">
                        <div v-if="post.author" class="flex items-center gap-2">
                            <img
                                v-if="post.author.avatar_url"
                                :src="post.author.avatar_url"
                                :alt="post.author.name"
                                class="h-6 w-6 rounded-full object-cover"
                            />
                            <span class="font-medium" :style="{ color: authorColor }">{{ post.author.name }}</span>
                            <span v-if="post.author.static_name" class="text-on-surface-variant/70">[{{ post.author.static_name }}]</span>
                        </div>
                        <span>· {{ formatDate(post.created_at) }}</span>

                        <div v-if="canEdit" class="ml-auto flex items-center gap-1">
                            <button
                                @click="showEditForm = true"
                                class="p-1.5 rounded hover:bg-white/5 text-on-surface-variant hover:text-on-surface transition"
                                :title="__('Edit')"
                            >
                                <span class="material-symbols-outlined text-sm">edit</span>
                            </button>
                            <button
                                @click="destroy"
                                class="p-1.5 rounded hover:bg-white/5 text-on-surface-variant hover:text-error transition"
                                :title="__('Delete')"
                            >
                                <span class="material-symbols-outlined text-sm">delete</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="canManage" class="mb-6 p-4 rounded-xl bg-surface-container-high/40 border border-primary/20">
                <div class="text-xs font-semibold uppercase tracking-wider text-primary mb-2">{{ __('Admin: change status') }}</div>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="(meta, key) in STATUS_META"
                        :key="key"
                        @click="changeStatus(key)"
                        :disabled="busy || post.status === key"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border transition"
                        :class="post.status === key
                            ? 'bg-primary/10 border-primary/40 text-primary cursor-default'
                            : 'bg-surface-container-high/60 border-white/5 text-on-surface-variant hover:border-primary/40'"
                    >
                        <span class="h-1.5 w-1.5 rounded-full" :class="meta.dotClass"></span>
                        {{ __(meta.label) }}
                    </button>
                </div>
            </div>

            <div class="mb-6 p-5 rounded-2xl bg-surface-container-high/60 border border-white/5">
                <SubtaskList
                    :subtasks="subtasks"
                    :post-id="post.id"
                    :can-manage="canManage"
                />
            </div>

            <div class="p-5 rounded-2xl bg-surface-container-high/60 border border-white/5">
                <CommentList
                    :comments="comments"
                    :post-id="post.id"
                    :is-authenticated="isAuthenticated"
                    @signin-required="handleSigninRequired"
                />
            </div>
        </div>

        <FeedbackForm
            :open="showEditForm"
            :post-id="post.id"
            :initial-title="post.title"
            :initial-body="post.body || ''"
            :initial-tag="post.tag || 'general'"
            :initial-images="post.images || []"
            @close="showEditForm = false"
            @updated="onUpdated"
        />

        <Transition name="fade">
            <div
                v-if="showAuthToast"
                class="fixed bottom-6 left-1/2 -translate-x-1/2 px-5 py-3 rounded-xl bg-surface-container-high border border-primary/40 text-on-surface shadow-2xl z-[120] flex items-center gap-3"
            >
                <span class="material-symbols-outlined text-primary">lock</span>
                <span class="text-sm">{{ __('Sign in to vote or post.') }}</span>
                <a :href="routes.bnetLogin()" class="ml-2 px-3 py-1 rounded-lg text-xs font-semibold bg-primary text-on-primary">
                    {{ __('Sign in') }}
                </a>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s, transform 0.3s;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
    transform: translate(-50%, 20px);
}
</style>
