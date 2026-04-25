import axios from 'axios';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export const feedbackApi = axios.create({
    headers: {
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
    },
});

// Routes are built from the meta-tag base URL so components stay
// environment-agnostic (local, prod, whatever subdomain).
export const routes = {
    vote: (postId) => `/feedback/${postId}/vote`,
    comments: (postId) => `/feedback/${postId}/comments`,
    deleteComment: (commentId) => `/feedback/comments/${commentId}`,
    post: (postId) => `/feedback/${postId}`,
    createPost: () => `/feedback`,
    updateStatus: (postId) => `/feedback/${postId}/status`,
    subtasks: (postId) => `/feedback/${postId}/subtasks`,
    updateSubtask: (subtaskId) => `/feedback/subtasks/${subtaskId}`,
    deleteSubtask: (subtaskId) => `/feedback/subtasks/${subtaskId}`,
    reorderSubtasks: (postId) => `/feedback/${postId}/subtasks/reorder`,
    uploads: () => `/feedback/uploads`,
    // Pass the current page as `redirect_to` so BattleNetController returns
    // the user here after OAuth instead of forcing onboarding.
    bnetLogin: () => {
        if (typeof window === 'undefined') return '/auth/battlenet/redirect';
        const back = encodeURIComponent(window.location.pathname + window.location.search);
        return `/auth/battlenet/redirect?redirect_to=${back}`;
    },
};

// Labels and tailwind classes for the 5 post statuses — keeping the
// mapping here means StatusBadge/Card/Kanban all read from one source.
export const STATUS_META = {
    under_review: {
        label: 'Under Review',
        classes: 'bg-white/5 text-on-surface-variant',
        dotClass: 'bg-on-surface-variant',
    },
    planned: {
        label: 'Planned',
        classes: 'bg-primary/10 text-primary',
        dotClass: 'bg-primary',
    },
    in_progress: {
        label: 'In Progress',
        classes: 'bg-amber-500/10 text-amber-400',
        dotClass: 'bg-amber-400',
    },
    done: {
        label: 'Done',
        classes: 'bg-success-neon/10 text-success-neon',
        dotClass: 'bg-success-neon',
    },
    closed: {
        label: 'Closed',
        classes: 'bg-error/10 text-error',
        dotClass: 'bg-error',
    },
};

export const SUBTASK_STATUS_META = {
    todo: { label: 'Todo', icon: 'radio_button_unchecked', colorClass: 'text-on-surface-variant' },
    in_progress: { label: 'In Progress', icon: 'change_circle', colorClass: 'text-amber-400' },
    done: { label: 'Done', icon: 'check_circle', colorClass: 'text-success-neon' },
};

// Tag = which app domain the post is about. One per post. Ordered as shown.
// `label` is the full name (used on cards/detail badges).
// `shortLabel` is a compact form used in filter chips to fit on one row.
export const TAG_META = {
    raid_events: { label: 'Raid Events', shortLabel: 'FB:Raids', icon: 'event', classes: 'bg-red-500/10 text-red-400 border-red-500/30' },
    boss_planner: { label: 'Boss Planner', shortLabel: 'FB:Tactics', icon: 'map', classes: 'bg-purple-500/10 text-purple-400 border-purple-500/30' },
    roster: { label: 'Roster', shortLabel: 'FB:Roster', icon: 'groups', classes: 'bg-blue-500/10 text-blue-400 border-blue-500/30' },
    gear: { label: 'Gear', shortLabel: 'FB:Gear', icon: 'shield', classes: 'bg-rose-500/10 text-rose-400 border-rose-500/30' },
    ai_analysis: { label: 'AI Analysis', shortLabel: 'FB:AI', icon: 'psychology', classes: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30' },
    treasury: { label: 'Treasury', shortLabel: 'FB:Treasury', icon: 'payments', classes: 'bg-yellow-500/10 text-yellow-400 border-yellow-500/30' },
    character: { label: 'Character & Profile', shortLabel: 'FB:Profile', icon: 'person', classes: 'bg-teal-500/10 text-teal-400 border-teal-500/30' },
    discord: { label: 'Discord', shortLabel: 'FB:Discord', icon: 'forum', classes: 'bg-indigo-500/10 text-indigo-400 border-indigo-500/30' },
    admin: { label: 'Settings & Admin', shortLabel: 'FB:Settings', icon: 'settings', classes: 'bg-slate-500/10 text-slate-400 border-slate-500/30' },
    bug: { label: 'Bug', shortLabel: 'FB:Bug', icon: 'bug_report', classes: 'bg-rose-700/10 text-rose-400 border-rose-700/30' },
    general: { label: 'General', shortLabel: 'FB:Other', icon: 'tag', classes: 'bg-white/5 text-on-surface-variant border-white/10' },
};

export function tagMeta(tag) {
    return TAG_META[tag] || TAG_META.general;
}

// WoW class colours — reused from roster/event code.
export const CLASS_COLORS = {
    'Death Knight': '#C41E3A',
    'Demon Hunter': '#A330C9',
    Druid: '#FF7C0A',
    Evoker: '#33937F',
    Hunter: '#AAD372',
    Mage: '#3FC7EB',
    Monk: '#00FF98',
    Paladin: '#F48CBA',
    Priest: '#FFFFFF',
    Rogue: '#FFF468',
    Shaman: '#0070DD',
    Warlock: '#8788EE',
    Warrior: '#C69B6D',
};

export function classColor(playableClass) {
    return CLASS_COLORS[playableClass] || 'inherit';
}
