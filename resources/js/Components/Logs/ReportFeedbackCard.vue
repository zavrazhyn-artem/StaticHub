<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
import { useNotifications } from '@/composables/useNotifications.js';

const { __ } = useTranslation();
const { push: pushNotification, dismiss: dismissNotification } = useNotifications();

const props = defineProps({
    reportId:        { type: [Number, String], required: true },
    storeUrlPattern: { type: String, required: true },  // '/s/{static}/logs/{report}/feedback'
    showUrlPattern:  { type: String, required: true },
});

const cardEl = ref(null);
let toastTimer = null;
let toastId = null;

// State -----------------------------------------------------------------------

const loading = ref(true);
const submitting = ref(false);
const dismissed = ref(false);  // local-only toggle for "Не зараз"

const showChatRating = ref(false);
const likedPool = ref([]);
const dislikedPool = ref([]);

const reportRating = ref(0);
const chatRating = ref(0);
const likedTags = ref([]);
const dislikedTags = ref([]);
const comment = ref('');
const submittedAt = ref(null);
const errorMsg = ref('');

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

// Derived ---------------------------------------------------------------------

const hasSubmitted = computed(() => !!submittedAt.value);

const showLikedSection = computed(() => reportRating.value >= 4);
const showDislikedSection = computed(() => reportRating.value > 0 && reportRating.value <= 4);

const commentRequired = computed(() => reportRating.value > 0 && reportRating.value <= 2);
const commentSuggested = computed(() => reportRating.value === 3);

const canSubmit = computed(() => {
    if (reportRating.value < 1) return false;
    if (commentRequired.value && !comment.value.trim()) return false;
    return true;
});

// Tag labels — keep slugs in payload, translate for display
const tagLabel = (slug) => {
    const map = {
        // liked
        accurate_data:     __('Accurate data'),
        actionable:        __('Actionable advice'),
        per_pull_value:    __('Per-pull breakdown is valuable'),
        good_tone:         __('Good coaching tone'),
        spec_aware:        __('Understands my spec'),
        comprehensive:     __('Comprehensive analysis'),
        boss_timeline:     __('Boss timing references help'),
        // disliked
        inaccurate_data:   __('Inaccurate numbers'),
        hallucinations:    __('Made up facts'),
        missing_context:   __('Missing important context'),
        too_long:          __('Too long'),
        tone:              __('Tone is off'),
        wrong_spec_advice: __('Advice not for my spec'),
        generic:           __('Generic / not specific'),
        repetitive:        __('Repetitive'),
    };
    return map[slug] ?? slug;
};

// Lifecycle -------------------------------------------------------------------

async function loadForm() {
    loading.value = true;
    errorMsg.value = '';
    try {
        const res = await fetch(props.showUrlPattern, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
        if (!res.ok) {
            const reason = res.status === 401 ? __('Not signed in')
                         : res.status === 403 ? __('Access denied')
                         : `HTTP ${res.status}`;
            errorMsg.value = __('Could not load feedback form') + ` (${reason})`;
            return;
        }
        const data = await res.json();

        showChatRating.value = !!data.show_chat_rating;
        likedPool.value      = data.liked_tag_pool || [];
        dislikedPool.value   = data.disliked_tag_pool || [];

        if (data.existing) {
            reportRating.value = data.existing.report_rating ?? 0;
            chatRating.value   = data.existing.chat_rating ?? 0;
            likedTags.value    = data.existing.liked_tags ?? [];
            dislikedTags.value = data.existing.disliked_tags ?? [];
            comment.value      = data.existing.comment ?? '';
            submittedAt.value  = data.existing.submitted_at;
        }
    } catch (e) {
        errorMsg.value = __('Network error') + ' — ' + (e?.message ?? '');
        console.error('Feedback form load failed:', e);
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    loadForm();
    scheduleToast();
});

onBeforeUnmount(() => {
    if (toastTimer) clearTimeout(toastTimer);
    if (toastId) dismissNotification(toastId);
});

/**
 * Show a top-right toast 30s after page load IF the user hasn't already
 * submitted feedback for this report and hasn't dismissed this toast before
 * (persistKey scoped per report ID). Clicking the action smooth-scrolls
 * to the card.
 */
function scheduleToast() {
    toastTimer = setTimeout(() => {
        if (hasSubmitted.value || dismissed.value) return;

        toastId = pushNotification({
            type: 'info',
            icon: 'rate_review',
            title: __('Leave a feedback for this report'),
            body: __('At the bottom of the page is a feedback form — your rating shapes the next AI iteration.'),
            persistKey: `feedback_cta_${props.reportId}`,
            dismissible: true,
            action: {
                label: __('Scroll to feedback'),
                icon: 'arrow_downward',
                onClick: scrollToCard,
            },
        });
    }, 30_000);
}

function scrollToCard() {
    cardEl.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// Actions ---------------------------------------------------------------------

/**
 * Vue auto-unwraps refs in templates, so passing `likedTags` from a template
 * delivers the unwrapped array — not the ref. Toggling needs the ref to
 * mutate, so resolve it here by name.
 */
function toggleTag(which, tag) {
    const list = which === 'liked' ? likedTags : dislikedTags;
    const i = list.value.indexOf(tag);
    if (i >= 0) list.value.splice(i, 1);
    else list.value.push(tag);
}

async function submit() {
    if (!canSubmit.value || submitting.value) return;
    submitting.value = true;
    errorMsg.value = '';

    try {
        const res = await fetch(props.storeUrlPattern, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                report_rating: reportRating.value,
                chat_rating:   showChatRating.value && chatRating.value > 0 ? chatRating.value : null,
                liked_tags:    likedTags.value,
                disliked_tags: dislikedTags.value,
                comment:       comment.value.trim() || null,
            }),
        });

        if (!res.ok) {
            const body = await res.json().catch(() => ({}));
            errorMsg.value = body?.errors?.comment?.[0] || body?.message || __('Submission failed');
            return;
        }

        const data = await res.json();
        submittedAt.value = data.feedback?.submitted_at ?? new Date().toISOString();
        // Successful submit — kill any pending toast nudge.
        if (toastId) { dismissNotification(toastId); toastId = null; }
        if (toastTimer) { clearTimeout(toastTimer); toastTimer = null; }
    } catch (e) {
        console.error(e);
        errorMsg.value = __('Network error — please try again');
    } finally {
        submitting.value = false;
    }
}

function startEditing() {
    submittedAt.value = null;
}
</script>

<template>
    <div v-if="!dismissed" ref="cardEl" class="bg-surface-container-low border border-white/5 rounded-3xl overflow-hidden shadow-2xl mt-8 mb-12 scroll-mt-8">
        <!-- Header -->
        <div class="bg-amber-400/5 border-b border-white/5 px-8 py-4 flex items-center justify-between">
            <h3 class="text-amber-400 font-headline text-xs font-black uppercase tracking-[0.2em] flex items-center gap-3">
                <span class="material-symbols-outlined text-lg">rate_review</span>
                {{ hasSubmitted ? __('Feedback received') : __('How was this report?') }}
            </h3>
            <button v-if="!hasSubmitted" @click="dismissed = true"
                    class="text-on-surface-variant hover:text-white text-3xs uppercase tracking-wider font-bold transition-colors">
                {{ __('Not now') }}
            </button>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="p-8 text-center text-on-surface-variant text-3xs uppercase tracking-wider">
            <span class="material-symbols-outlined animate-spin align-middle mr-2">sync</span>
            {{ __('Loading...') }}
        </div>

        <!-- Load error with retry -->
        <div v-else-if="errorMsg && likedPool.length === 0" class="p-8 text-center">
            <span class="material-symbols-outlined text-error mb-2" style="font-size: 32px">error</span>
            <p class="text-error text-sm mb-4">{{ errorMsg }}</p>
            <button @click="loadForm"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-white/5 hover:bg-white/10 border border-white/10 rounded text-3xs font-bold uppercase tracking-wider text-on-surface-variant hover:text-white transition-all">
                <span class="material-symbols-outlined text-sm">refresh</span>
                {{ __('Retry') }}
            </button>
        </div>

        <!-- Submitted state -->
        <div v-else-if="hasSubmitted" class="p-8 text-center">
            <div class="text-amber-400 text-4xl mb-3">
                <span class="material-symbols-outlined" style="font-size: 48px">check_circle</span>
            </div>
            <p class="text-on-surface text-sm mb-4">{{ __('Thank you — your feedback shapes the next iteration of the AI.') }}</p>
            <button @click="startEditing"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-white/5 hover:bg-white/10 border border-white/10 rounded text-3xs font-bold uppercase tracking-wider text-on-surface-variant hover:text-white transition-all">
                <span class="material-symbols-outlined text-sm">edit</span>
                {{ __('Edit feedback') }}
            </button>
        </div>

        <!-- Form -->
        <div v-else class="p-8 space-y-6">
            <!-- Report rating -->
            <div>
                <label class="block text-3xs font-bold text-on-surface-variant uppercase tracking-wider mb-3">
                    {{ __('Report quality') }}
                </label>
                <div class="flex gap-2">
                    <button v-for="n in 5" :key="n" @click="reportRating = n" type="button"
                            class="text-4xl transition-all hover:scale-110"
                            :class="n <= reportRating ? 'text-amber-400' : 'text-on-surface-variant/30 hover:text-amber-400/50'">
                        <span class="material-symbols-outlined" :style="{ fontVariationSettings: n <= reportRating ? '\'FILL\' 1' : '\'FILL\' 0' }">star</span>
                    </button>
                </div>
            </div>

            <!-- Chat rating (conditional) -->
            <div v-if="showChatRating">
                <label class="block text-3xs font-bold text-on-surface-variant uppercase tracking-wider mb-3">
                    {{ __('AI Chat quality') }}
                </label>
                <div class="flex gap-2">
                    <button v-for="n in 5" :key="n" @click="chatRating = n" type="button"
                            class="text-3xl transition-all hover:scale-110"
                            :class="n <= chatRating ? 'text-indigo-400' : 'text-on-surface-variant/30 hover:text-indigo-400/50'">
                        <span class="material-symbols-outlined" :style="{ fontVariationSettings: n <= chatRating ? '\'FILL\' 1' : '\'FILL\' 0' }">star</span>
                    </button>
                </div>
            </div>

            <!-- Liked tags -->
            <div v-if="reportRating > 0">
                <details :open="showLikedSection" class="group">
                    <summary class="flex items-center gap-2 cursor-pointer text-3xs font-bold uppercase tracking-wider mb-3"
                             :class="showLikedSection ? 'text-success-neon' : 'text-on-surface-variant'">
                        <span class="material-symbols-outlined text-sm">thumb_up</span>
                        {{ __('What you liked') }}
                        <span class="material-symbols-outlined text-sm transition-transform group-open:rotate-180 ml-auto">expand_more</span>
                    </summary>
                    <div class="flex flex-wrap gap-2 mt-3">
                        <button v-for="tag in likedPool" :key="tag" @click="toggleTag('liked', tag)" type="button"
                                class="px-3 py-1.5 rounded text-4xs font-bold uppercase tracking-wider border transition-all"
                                :class="likedTags.includes(tag)
                                    ? 'bg-success-neon/20 border-success-neon/50 text-success-neon'
                                    : 'bg-white/5 border-white/10 text-on-surface-variant hover:bg-white/10 hover:text-white'">
                            {{ tagLabel(tag) }}
                        </button>
                    </div>
                </details>
            </div>

            <!-- Disliked tags -->
            <div v-if="reportRating > 0 && reportRating <= 4">
                <details :open="reportRating <= 3" class="group">
                    <summary class="flex items-center gap-2 cursor-pointer text-3xs font-bold uppercase tracking-wider mb-3"
                             :class="reportRating <= 3 ? 'text-error' : 'text-on-surface-variant'">
                        <span class="material-symbols-outlined text-sm">thumb_down</span>
                        {{ __('What to improve') }}
                        <span class="material-symbols-outlined text-sm transition-transform group-open:rotate-180 ml-auto">expand_more</span>
                    </summary>
                    <div class="flex flex-wrap gap-2 mt-3">
                        <button v-for="tag in dislikedPool" :key="tag" @click="toggleTag('disliked', tag)" type="button"
                                class="px-3 py-1.5 rounded text-4xs font-bold uppercase tracking-wider border transition-all"
                                :class="dislikedTags.includes(tag)
                                    ? 'bg-error/20 border-error/50 text-error'
                                    : 'bg-white/5 border-white/10 text-on-surface-variant hover:bg-white/10 hover:text-white'">
                            {{ tagLabel(tag) }}
                        </button>
                    </div>
                </details>
            </div>

            <!-- Comment -->
            <div v-if="reportRating > 0">
                <label class="block text-3xs font-bold text-on-surface-variant uppercase tracking-wider mb-3">
                    {{ __('Comment') }}
                    <span v-if="commentRequired" class="text-error">({{ __('required') }})</span>
                    <span v-else-if="commentSuggested" class="text-amber-400">({{ __('suggested') }})</span>
                    <span v-else class="text-on-surface-variant/60">({{ __('optional') }})</span>
                </label>
                <textarea v-model="comment" rows="3" maxlength="2000"
                          class="w-full bg-black/20 border border-white/10 rounded-lg px-4 py-3 text-sm text-on-surface placeholder:text-on-surface-variant/40 focus:border-amber-400/50 focus:outline-none transition-colors"
                          :placeholder="reportRating >= 4 ? __('What stood out as helpful?') : __('Tell us what we should fix...')">
                </textarea>
            </div>

            <!-- Error -->
            <p v-if="errorMsg" class="text-3xs text-error font-bold uppercase tracking-wider">{{ errorMsg }}</p>

            <!-- Submit -->
            <div class="flex justify-end">
                <button @click="submit" :disabled="!canSubmit || submitting"
                        class="inline-flex items-center gap-2 px-6 py-3 rounded text-3xs font-bold uppercase tracking-wider transition-all disabled:opacity-30 disabled:cursor-not-allowed"
                        :class="canSubmit
                            ? 'bg-amber-400 hover:bg-amber-500 text-black shadow-[0_0_15px_rgba(251,191,36,0.4)]'
                            : 'bg-white/10 text-on-surface-variant'">
                    <span v-if="submitting" class="material-symbols-outlined text-sm animate-spin">sync</span>
                    <span v-else class="material-symbols-outlined text-sm">send</span>
                    {{ submitting ? __('Submitting...') : __('Submit feedback') }}
                </button>
            </div>
        </div>
    </div>
</template>
