import './bootstrap';

import { createApp, defineAsyncComponent } from 'vue';
import * as Sentry from '@sentry/vue';

// Eager (shared across layout or tiny) — loaded on every page anyway
import AlertBanner from './Components/UI/AlertBanner.vue';
import SyncStatusWidget from './Components/UI/SyncStatusWidget.vue';

// Async factory — Vite emits a separate chunk per component, loaded on demand
const lazy = (loader) => defineAsyncComponent(loader);

const app = createApp({});

Sentry.init({
    app,
    dsn: import.meta.env.VITE_SENTRY_DSN,
    environment: import.meta.env.VITE_APP_ENV || 'local',

    integrations: [
        Sentry.browserTracingIntegration(),
        Sentry.replayIntegration(),
    ],
    tracesSampleRate: 1.0,
    replaysSessionSampleRate: 0.1,
    replaysOnErrorSampleRate: 1.0,
});

app.config.globalProperties.__ = (key, replace = {}) => {
    const translations = window.translations || {};
    let translation = translations[key] || key;

    Object.keys(replace).forEach(r => {
        translation = translation.replace(`{${r}}`, replace[r]);
        translation = translation.replace(`:${r}`, replace[r]);
    });

    return translation;
};

// Shared UI primitives — eager
app.component('alert-banner', AlertBanner);
app.component('sync-status-widget', SyncStatusWidget);

// Page-level components — lazy-loaded per route
app.component('roster-overview',           lazy(() => import('./Components/Roster/RosterOverview.vue')));
app.component('unified-roster',            lazy(() => import('./Components/Roster/UnifiedRoster.vue')));
app.component('treasury-dashboard',        lazy(() => import('./Components/Treasury/TreasuryDashboard.vue')));
app.component('schedule-calendar',         lazy(() => import('./Components/Schedule/ScheduleCalendar.vue')));
app.component('event-details',             lazy(() => import('./Components/Raid/EventDetails.vue')));
app.component('boss-planner-page',         lazy(() => import('./Components/Raid/BossPlanner/BossPlannerPage.vue')));
app.component('shared-plan-view',          lazy(() => import('./Components/Raid/BossPlanner/SharedPlanView.vue')));
app.component('join-static',               lazy(() => import('./Components/Statics/JoinStatic.vue')));
app.component('logs-index',                lazy(() => import('./Components/Logs/LogsIndex.vue')));
app.component('log-show',                  lazy(() => import('./Components/Logs/LogShow.vue')));
app.component('settings-tabs',             lazy(() => import('./Components/Settings/SettingsTabs.vue')));
app.component('settings-logs',             lazy(() => import('./Components/Settings/SettingsLogs.vue')));
app.component('settings-schedule',         lazy(() => import('./Components/Settings/SettingsSchedule.vue')));
app.component('settings-discord',          lazy(() => import('./Components/Settings/SettingsDiscord.vue')));
app.component('settings-profile',          lazy(() => import('./Components/Settings/SettingsProfile.vue')));
app.component('gear-management',           lazy(() => import('./Components/Gear/GearManagement.vue')));
app.component('static-setup',              lazy(() => import('./Components/Statics/StaticSetup.vue')));
app.component('consumables-planner',       lazy(() => import('./Components/Treasury/ConsumablesPlanner.vue')));
app.component('consumable-card',           lazy(() => import('./Components/Treasury/ConsumableCard.vue')));
app.component('transaction-history',       lazy(() => import('./Components/Treasury/TransactionHistory.vue')));
app.component('dashboard-view',            lazy(() => import('./Components/Dashboard/DashboardView.vue')));
app.component('transfer-ownership-select', lazy(() => import('./Components/Profile/TransferOwnershipSelect.vue')));
app.component('character-spec-picker',     lazy(() => import('./Components/Character/CharacterSpecPicker.vue')));
app.component('characters-page',           lazy(() => import('./Components/Character/CharactersPage.vue')));
app.component('onboarding-stepper',        lazy(() => import('./Components/Onboarding/OnboardingStepper.vue')));
app.component('landing-page',              lazy(() => import('./Components/Landing/LandingPage.vue')));
app.component('feedback-list',             lazy(() => import('./Components/Feedback/FeedbackList.vue')));
app.component('feedback-detail',           lazy(() => import('./Components/Feedback/FeedbackDetail.vue')));
app.component('roadmap-kanban',            lazy(() => import('./Components/Feedback/RoadmapKanban.vue')));

// Mount Vue to the element with id="app" if it exists
if (document.getElementById('app')) {
    app.mount('#app');
}

// Force reload on bfcache restore (prevents stale Vue state on browser back)
window.addEventListener('pageshow', (e) => {
    if (e.persisted) window.location.reload();
});

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

// ---------------------------------------------------------------------------
// Wowhead tooltip viewport containment
// ---------------------------------------------------------------------------
// The Wowhead script positions tooltips with absolute top/left which can push
// them below the viewport, causing the page to scroll. This observer watches
// for tooltip elements and repositions them so they stay within the viewport.
(() => {
    const PADDING = 8;

    const isTooltipEl = (el) => el?.id?.startsWith?.('wowhead-tooltip');

    const clamp = (el) => {
        if (!el?.style) return;

        // Wowhead calculates top/left for position:absolute (includes scrollY).
        // CSS forces position:fixed, so subtract scroll to convert to viewport coords.
        let top = parseFloat(el.style.top);
        let left = parseFloat(el.style.left);
        if (isNaN(top) || isNaN(left)) return;

        top -= window.scrollY;
        left -= window.scrollX;

        const rect = el.getBoundingClientRect();
        const h = rect.height || el.offsetHeight;
        const w = rect.width || el.offsetWidth;
        if (!h) return;

        // Clamp within viewport
        if (top + h > window.innerHeight - PADDING) {
            top = Math.max(PADDING, window.innerHeight - h - PADDING);
        }
        if (top < PADDING) top = PADDING;

        if (left + w > window.innerWidth - PADDING) {
            left = Math.max(PADDING, window.innerWidth - w - PADDING);
        }
        if (left < PADDING) left = PADDING;

        el.style.top = top + 'px';
        el.style.left = left + 'px';
    };

    const observer = new MutationObserver((mutations) => {
        for (const m of mutations) {
            if (m.type === 'attributes' && m.attributeName === 'style' && isTooltipEl(m.target)) {
                clamp(m.target);
            }
            for (const node of m.addedNodes) {
                if (node.nodeType === 1 && isTooltipEl(node)) {
                    clamp(node);
                }
            }
        }
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['style'],
    });
})();
