<script setup>
import { computed } from 'vue';
import StatusBadge from './StatusBadge.vue';
import TagBadge from './TagBadge.vue';
import VoteButton from './VoteButton.vue';
import { classColor } from './api.js';

const props = defineProps({
    post: { type: Object, required: true },
    isAuthenticated: { type: Boolean, default: false },
});

const emit = defineEmits(['signin-required']);

const authorColor = computed(() => classColor(props.post.author?.playable_class));
const detailUrl = computed(() => `/feedback/${props.post.id}`);
</script>

<template>
    <a
        :href="detailUrl"
        class="group flex gap-4 p-4 rounded-2xl bg-surface-container-high/60 border border-white/5 hover:border-primary/40 transition-all"
    >
        <VoteButton
            :post-id="post.id"
            :initial-voted="!!post.user_has_voted"
            :initial-count="post.votes_count"
            :is-authenticated="isAuthenticated"
            variant="vertical"
            @signin-required="emit('signin-required')"
        />

        <div class="flex-1 min-w-0 flex flex-col gap-2">
            <div class="flex items-start justify-between gap-3">
                <h3 class="text-base font-semibold text-on-surface leading-snug group-hover:text-primary transition-colors">
                    {{ post.title }}
                </h3>
                <div class="flex items-center gap-1.5 shrink-0 mt-0.5">
                    <TagBadge v-if="post.tag" :tag="post.tag" size="sm" />
                    <StatusBadge :status="post.status" size="sm" />
                </div>
            </div>

            <p v-if="post.excerpt" class="text-sm text-on-surface-variant line-clamp-2">
                {{ post.excerpt }}
            </p>

            <div class="flex items-center gap-3 text-xs text-on-surface-variant mt-auto">
                <div v-if="post.author" class="flex items-center gap-2">
                    <img
                        v-if="post.author.avatar_url"
                        :src="post.author.avatar_url"
                        :alt="post.author.name"
                        class="h-5 w-5 rounded-full object-cover"
                    />
                    <span class="font-medium" :style="{ color: authorColor }">
                        {{ post.author.name }}
                    </span>
                    <span v-if="post.author.static_name" class="text-on-surface-variant/70">
                        [{{ post.author.static_name }}]
                    </span>
                </div>

                <span v-if="post.comments_count > 0" class="inline-flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">chat_bubble</span>
                    {{ post.comments_count }}
                </span>

                <span v-if="post.subtasks_count > 0" class="inline-flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">checklist</span>
                    {{ post.subtasks_count }}
                </span>

                <span v-if="post.images?.length" class="inline-flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">image</span>
                    {{ post.images.length }}
                </span>
            </div>
        </div>
    </a>
</template>
