import './bootstrap';

import { createApp } from 'vue';
import * as Sentry from '@sentry/vue';
import RosterOverview from './Components/Roster/RosterOverview.vue';
import UnifiedRoster from './Components/Roster/UnifiedRoster.vue';
import SyncStatusWidget from './Components/UI/SyncStatusWidget.vue';
import TreasuryDashboard from './Components/Treasury/TreasuryDashboard.vue';
import ScheduleCalendar from './Components/Schedule/ScheduleCalendar.vue';
import EventDetails from './Components/Raid/EventDetails.vue';
import BossPlannerPage from './Components/Raid/BossPlanner/BossPlannerPage.vue';
import SharedPlanView from './Components/Raid/BossPlanner/SharedPlanView.vue';
import JoinStatic from './Components/Statics/JoinStatic.vue';
import LogsIndex from './Components/Logs/LogsIndex.vue';
import LogShow from './Components/Logs/LogShow.vue';
import SettingsTabs from './Components/Settings/SettingsTabs.vue';
import SettingsLogs from './Components/Settings/SettingsLogs.vue';
import SettingsSchedule from './Components/Settings/SettingsSchedule.vue';
import SettingsDiscord from './Components/Settings/SettingsDiscord.vue';
import SettingsProfile from './Components/Settings/SettingsProfile.vue';
import GearManagement from './Components/Gear/GearManagement.vue';
import StaticSetup from './Components/Statics/StaticSetup.vue';
import ConsumablesPlanner from './Components/Treasury/ConsumablesPlanner.vue';
import TransactionHistory from './Components/Treasury/TransactionHistory.vue';
import ConsumableCard from './Components/Treasury/ConsumableCard.vue';
import DashboardView from './Components/Dashboard/DashboardView.vue';
import TransferOwnershipSelect from './Components/Profile/TransferOwnershipSelect.vue';
import CharacterSpecPicker from './Components/Character/CharacterSpecPicker.vue';
import CharactersPage from './Components/Character/CharactersPage.vue';
import OnboardingStepper from './Components/Onboarding/OnboardingStepper.vue';
import AlertBanner from './Components/UI/AlertBanner.vue';
import LandingPage from './Components/Landing/LandingPage.vue';




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

app.component('roster-overview', RosterOverview);
app.component('unified-roster', UnifiedRoster);
app.component('sync-status-widget', SyncStatusWidget);
app.component('treasury-dashboard', TreasuryDashboard);
app.component('schedule-calendar', ScheduleCalendar);
app.component('event-details', EventDetails);
app.component('boss-planner-page', BossPlannerPage);
app.component('shared-plan-view', SharedPlanView);
app.component('join-static', JoinStatic);
app.component('logs-index', LogsIndex);
app.component('log-show', LogShow);
app.component('settings-tabs', SettingsTabs);
app.component('settings-logs', SettingsLogs);
app.component('settings-schedule', SettingsSchedule);
app.component('settings-discord', SettingsDiscord);
app.component('settings-profile', SettingsProfile);
app.component('gear-management', GearManagement);
app.component('static-setup', StaticSetup);
app.component('consumables-planner', ConsumablesPlanner);
app.component('consumable-card', ConsumableCard);
app.component('transaction-history', TransactionHistory);
app.component('dashboard-view', DashboardView);
app.component('transfer-ownership-select', TransferOwnershipSelect);
app.component('character-spec-picker', CharacterSpecPicker);
app.component('characters-page', CharactersPage);
app.component('onboarding-stepper', OnboardingStepper);
app.component('alert-banner', AlertBanner);
app.component('landing-page', LandingPage);

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
