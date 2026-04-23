<script setup>
import { ref, computed } from 'vue';
import FeedbackCard from './FeedbackCard.vue';
import FeedbackForm from './FeedbackForm.vue';
import FeedbackTabs from './FeedbackTabs.vue';
import { routes, STATUS_META, TAG_META } from './api.js';

const props = defineProps({
    initialData: { type: Object, required: true },
    isAuthenticated: { type: Boolean, default: false },
    canManage: { type: Boolean, default: false },
});

const posts = ref(props.initialData.posts);
const counts = ref(props.initialData.counts || {});
const tagCounts = ref(props.initialData.tag_counts || {});
const filters = ref({
    status: props.initialData.filters.status || 'all',
    tag: props.initialData.filters.tag || 'all',
    sort: props.initialData.filters.sort || 'votes',
    search: props.initialData.filters.search || '',
});

const showForm = ref(false);
const showAuthToast = ref(false);

const totalCount = computed(() => Object.values(counts.value).reduce((a, b) => a + b, 0));

const statusTabs = computed(() => [
    { key: 'all', label: 'All', count: totalCount.value },
    ...Object.keys(STATUS_META).map((key) => ({
        key,
        label: STATUS_META[key].label, // raw English key; template passes through __()
        count: counts.value[key] || 0,
        dotClass: STATUS_META[key].dotClass,
    })),
]);

const tagTabs = computed(() => Object.keys(TAG_META).map((key) => ({
    key,
    label: TAG_META[key].label,
    shortLabel: TAG_META[key].shortLabel || TAG_META[key].label,
    icon: TAG_META[key].icon,
    classes: TAG_META[key].classes,
    count: tagCounts.value[key] || 0,
})));

function updateFilter(key, value) {
    filters.value[key] = value;
    syncUrl();
}

function syncUrl() {
    const params = new URLSearchParams();
    if (filters.value.status && filters.value.status !== 'all') params.set('status', filters.value.status);
    if (filters.value.tag && filters.value.tag !== 'all') params.set('tag', filters.value.tag);
    if (filters.value.sort && filters.value.sort !== 'votes') params.set('sort', filters.value.sort);
    if (filters.value.search) params.set('search', filters.value.search);

    const query = params.toString();
    window.location.href = `/feedback${query ? '?' + query : ''}`;
}

function handleSigninRequired() {
    if (props.isAuthenticated) return;
    showAuthToast.value = true;
    setTimeout(() => (showAuthToast.value = false), 3500);
}

function openNewPost() {
    if (!props.isAuthenticated) {
        handleSigninRequired();
        return;
    }
    showForm.value = true;
}
</script>

<template>
    <div class="pb-16">
        <div class="w-full lg:w-[85%] mx-auto">
            <FeedbackTabs active="feedback" />

            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-headline uppercase tracking-tighter text-on-surface">
                        {{ __('Feedback') }}
                    </h1>
                    <p class="text-sm text-on-surface-variant mt-1">
                        {{ __('Vote on ideas, propose features, report bugs — we read everything.') }}
                    </p>
                </div>
                <button
                    @click="openNewPost"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-primary text-on-primary font-semibold text-sm hover:bg-primary/90 active:scale-95 transition"
                >
                    <span class="material-symbols-outlined text-base">add</span>
                    {{ __('New suggestion') }}
                </button>
            </div>

            <div class="flex items-center gap-1.5 mb-3 overflow-x-auto feedback-scroll-row">
                <span class="text-3xs font-semibold uppercase tracking-wider text-on-surface-variant/70 shrink-0 hidden sm:block">
                    {{ __('Category') }}
                </span>
                <button
                    @click="updateFilter('tag', 'all')"
                    class="shrink-0 inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-4xs font-semibold uppercase tracking-wider transition"
                    :class="filters.tag === 'all'
                        ? 'bg-primary/10 border-primary/40 text-primary'
                        : 'bg-surface-container-high/60 border-white/5 text-on-surface-variant hover:border-primary/40'"
                >
                    {{ __('All') }}
                </button>
                <button
                    v-for="t in tagTabs"
                    :key="t.key"
                    @click="updateFilter('tag', t.key)"
                    :title="__(t.label) + (t.count ? ' (' + t.count + ')' : '')"
                    class="shrink-0 inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-4xs font-semibold uppercase tracking-wider transition"
                    :class="filters.tag === t.key
                        ? t.classes + ' ring-2 ring-primary/50'
                        : 'bg-surface-container-high/60 border-white/5 text-on-surface-variant hover:border-primary/40'"
                >
                    <span class="material-symbols-outlined text-xs leading-none">{{ t.icon }}</span>
                    <span>{{ __(t.shortLabel) }}</span>
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-2 mb-4">
                <span class="text-3xs font-semibold uppercase tracking-wider text-on-surface-variant/70 mr-1">{{ __('Status') }}:</span>
                <button
                    v-for="tab in statusTabs"
                    :key="tab.key"
                    @click="updateFilter('status', tab.key)"
                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold border transition"
                    :class="filters.status === tab.key
                        ? 'bg-primary/10 border-primary/40 text-primary'
                        : 'bg-surface-container-high/60 border-white/5 text-on-surface-variant hover:border-primary/40'"
                >
                    <span v-if="tab.dotClass" class="h-1.5 w-1.5 rounded-full" :class="tab.dotClass"></span>
                    {{ __(tab.label) }}
                    <span class="text-on-surface-variant/70">({{ tab.count }})</span>
                </button>
            </div>

            <div class="flex items-center justify-between gap-3 mb-5">
                <div class="relative flex-1 max-w-sm">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant/70 text-base">search</span>
                    <input
                        :value="filters.search"
                        @keyup.enter="updateFilter('search', $event.target.value)"
                        type="text"
                        :placeholder="__('Search posts…')"
                        class="w-full pl-10 pr-3 py-2 rounded-xl bg-surface-container-high/60 border border-white/5 focus:border-primary focus:outline-none text-on-surface text-sm"
                    />
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <span class="text-on-surface-variant">{{ __('Sort:') }}</span>
                    <button
                        @click="updateFilter('sort', 'votes')"
                        class="px-3 py-1.5 rounded-lg font-semibold transition"
                        :class="filters.sort === 'votes'
                            ? 'bg-primary/10 text-primary'
                            : 'text-on-surface-variant hover:bg-white/5'"
                    >
                        {{ __('Most voted') }}
                    </button>
                    <button
                        @click="updateFilter('sort', 'recent')"
                        class="px-3 py-1.5 rounded-lg font-semibold transition"
                        :class="filters.sort === 'recent'
                            ? 'bg-primary/10 text-primary'
                            : 'text-on-surface-variant hover:bg-white/5'"
                    >
                        {{ __('Recent') }}
                    </button>
                </div>
            </div>

            <div v-if="posts.length === 0" class="text-center py-16">
                <span class="material-symbols-outlined text-6xl text-on-surface-variant/40 mb-2">forum</span>
                <p class="text-on-surface-variant">{{ __('No posts yet. Be the first to suggest something.') }}</p>
            </div>

            <div v-else class="flex flex-col gap-3">
                <FeedbackCard
                    v-for="post in posts"
                    :key="post.id"
                    :post="post"
                    :is-authenticated="isAuthenticated"
                    @signin-required="handleSigninRequired"
                />
            </div>
        </div>

        <FeedbackForm
            :open="showForm"
            @close="showForm = false"
        />

        <Transition name="fade">
            <div
                v-if="showAuthToast"
                class="fixed bottom-6 left-1/2 -translate-x-1/2 px-5 py-3 rounded-xl bg-surface-container-high border border-primary/40 text-on-surface shadow-2xl z-[120] flex items-center gap-3"
            >
                <span class="material-symbols-outlined text-primary">lock</span>
                <span class="text-sm">{{ __('Sign in to vote or post.') }}</span>
                <a
                    :href="routes.bnetLogin()"
                    class="ml-2 px-3 py-1 rounded-lg text-xs font-semibold bg-primary text-on-primary"
                >
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
.feedback-scroll-row {
    scrollbar-width: none;
}
.feedback-scroll-row::-webkit-scrollbar {
    display: none;
}
</style>
