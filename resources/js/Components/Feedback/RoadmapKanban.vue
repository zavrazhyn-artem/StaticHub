<script setup>
import { ref, getCurrentInstance } from 'vue';
import StatusBadge from './StatusBadge.vue';
import TagBadge from './TagBadge.vue';
import VoteButton from './VoteButton.vue';
import FeedbackTabs from './FeedbackTabs.vue';
import { feedbackApi, routes, STATUS_META, classColor } from './api.js';

const { proxy } = getCurrentInstance();
const __ = (key, replace = {}) => proxy.__(key, replace);

const props = defineProps({
    payload: { type: Object, required: true },
    isAuthenticated: { type: Boolean, default: false },
    canManage: { type: Boolean, default: false },
});

const COLUMNS = [
    { key: 'under_review', label: 'Under Review' },
    { key: 'planned', label: 'Planned' },
    { key: 'in_progress', label: 'In Progress' },
    { key: 'done', label: 'Done' },
];

const columns = ref({ ...props.payload.columns });
const dragId = ref(null);
const showAuthToast = ref(false);

function handleSigninRequired() {
    if (props.isAuthenticated) return;
    showAuthToast.value = true;
    setTimeout(() => (showAuthToast.value = false), 3500);
}

function onDragStart(postId, fromStatus) {
    if (!props.canManage) return;
    dragId.value = { id: postId, from: fromStatus };
}

async function onDrop(toStatus) {
    if (!dragId.value || !props.canManage) return;
    const { id, from } = dragId.value;
    if (from === toStatus) {
        dragId.value = null;
        return;
    }

    // Optimistic move
    const idx = columns.value[from].findIndex((c) => c.id === id);
    if (idx === -1) return;
    const [card] = columns.value[from].splice(idx, 1);
    card.status = toStatus;
    columns.value[toStatus].unshift(card);
    dragId.value = null;

    try {
        await feedbackApi.patch(routes.updateStatus(id), { status: toStatus });
    } catch (e) {
        // Rollback
        card.status = from;
        columns.value[toStatus] = columns.value[toStatus].filter((c) => c.id !== id);
        columns.value[from].splice(idx, 0, card);
        alert(__('Failed to update status.'));
    }
}
</script>

<template>
    <div class="pb-16">
        <div class="w-full lg:w-[85%] mx-auto">
            <FeedbackTabs active="roadmap" />

            <div class="mb-6">
                <h1 class="text-2xl sm:text-3xl font-headline uppercase tracking-tighter text-on-surface">
                    {{ __('Roadmap') }}
                </h1>
                <p class="text-sm text-on-surface-variant mt-1">
                    {{ __("What we're working on, what's next, and what's shipped.") }}
                    <span v-if="canManage" class="text-primary">{{ __('Drag cards to change status.') }}</span>
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div
                    v-for="col in COLUMNS"
                    :key="col.key"
                    class="flex flex-col min-w-0"
                    @dragover.prevent
                    @drop="onDrop(col.key)"
                >
                    <div class="flex items-center justify-between px-3 py-2 mb-2 rounded-lg bg-surface-container-high/40 border border-white/5">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full" :class="STATUS_META[col.key].dotClass"></span>
                            <span class="text-sm font-headline uppercase tracking-wider text-on-surface">{{ __(col.label) }}</span>
                        </div>
                        <span class="text-xs text-on-surface-variant">{{ columns[col.key].length }}</span>
                    </div>

                    <div class="flex flex-col gap-2 min-h-[60vh] p-1 rounded-lg transition-colors"
                         :class="dragId && dragId.from !== col.key ? 'bg-primary/5 ring-2 ring-primary/30' : ''">
                        <div
                            v-for="post in columns[col.key]"
                            :key="post.id"
                            :draggable="canManage"
                            @dragstart="onDragStart(post.id, col.key)"
                            class="flex flex-col gap-2 p-3 rounded-xl bg-surface-container-high/60 border border-white/5 hover:border-primary/40 transition group"
                            :class="canManage ? 'cursor-grab active:cursor-grabbing' : ''"
                        >
                            <a :href="`/feedback/${post.id}`" class="block">
                                <TagBadge v-if="post.tag" :tag="post.tag" size="sm" class="mb-2" />
                                <h3 class="text-sm font-semibold text-on-surface group-hover:text-primary transition-colors leading-snug">
                                    {{ post.title }}
                                </h3>
                                <p v-if="post.excerpt" class="text-xs text-on-surface-variant line-clamp-2 mt-1">
                                    {{ post.excerpt }}
                                </p>
                            </a>

                            <div class="flex items-center justify-between gap-2 pt-1">
                                <div class="flex items-center gap-1.5 text-3xs text-on-surface-variant/80 min-w-0">
                                    <img
                                        v-if="post.author?.avatar_url"
                                        :src="post.author.avatar_url"
                                        :alt="post.author.name"
                                        class="h-4 w-4 rounded-full object-cover shrink-0"
                                    />
                                    <span class="truncate" :style="{ color: classColor(post.author?.playable_class) }">
                                        {{ post.author?.name }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 text-3xs text-on-surface-variant shrink-0">
                                    <span v-if="post.comments_count > 0" class="inline-flex items-center gap-0.5">
                                        <span class="material-symbols-outlined text-xs">chat_bubble</span>
                                        {{ post.comments_count }}
                                    </span>
                                    <span v-if="post.subtasks_count > 0" class="inline-flex items-center gap-0.5">
                                        <span class="material-symbols-outlined text-xs">checklist</span>
                                        {{ post.subtasks_count }}
                                    </span>
                                    <VoteButton
                                        :post-id="post.id"
                                        :initial-voted="!!post.user_has_voted"
                                        :initial-count="post.votes_count"
                                        :is-authenticated="isAuthenticated"
                                        variant="compact"
                                        @signin-required="handleSigninRequired"
                                    />
                                </div>
                            </div>
                        </div>

                        <div v-if="columns[col.key].length === 0" class="text-center py-8 text-3xs uppercase tracking-wider text-on-surface-variant/50">
                            {{ __('Empty') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Transition name="fade">
            <div
                v-if="showAuthToast"
                class="fixed bottom-6 left-1/2 -translate-x-1/2 px-5 py-3 rounded-xl bg-surface-container-high border border-primary/40 text-on-surface shadow-2xl z-[120] flex items-center gap-3"
            >
                <span class="material-symbols-outlined text-primary">lock</span>
                <span class="text-sm">{{ __('Sign in to vote.') }}</span>
                <a :href="routes.bnetLogin()" class="ml-2 px-3 py-1 rounded-lg text-xs font-semibold bg-primary text-on-primary">{{ __('Sign in') }}</a>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active { transition: opacity 0.3s, transform 0.3s; }
.fade-enter-from,
.fade-leave-to { opacity: 0; transform: translate(-50%, 20px); }
</style>
