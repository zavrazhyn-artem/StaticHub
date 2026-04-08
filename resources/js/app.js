import './bootstrap';

import { createApp } from 'vue';
import * as Sentry from '@sentry/vue';
import RosterOverview from './Components/Roster/RosterOverview.vue';
import UnifiedRoster from './Components/Roster/UnifiedRoster.vue';
import SyncStatusWidget from './Components/UI/SyncStatusWidget.vue';
import TreasuryDashboard from './Components/Treasury/TreasuryDashboard.vue';
import ScheduleCalendar from './Components/Schedule/ScheduleCalendar.vue';
import EventDetails from './Components/Raid/EventDetails.vue';
import JoinStatic from './Components/Statics/JoinStatic.vue';
import LogsIndex from './Components/Logs/LogsIndex.vue';
import LogShow from './Components/Logs/LogShow.vue';
import SettingsTabs from './Components/Settings/SettingsTabs.vue';
import SettingsLogs from './Components/Settings/SettingsLogs.vue';
import SettingsSchedule from './Components/Settings/SettingsSchedule.vue';
import SettingsDiscord from './Components/Settings/SettingsDiscord.vue';
import SettingsProfile from './Components/Settings/SettingsProfile.vue';
import StaticSetup from './Components/Statics/StaticSetup.vue';
import ConsumablesPlanner from './Components/Treasury/ConsumablesPlanner.vue';
import TransactionHistory from './Components/Treasury/TransactionHistory.vue';
import ConsumableCard from './Components/Treasury/ConsumableCard.vue';
import DashboardView from './Components/Dashboard/DashboardView.vue';
import TransferOwnershipSelect from './Components/Profile/TransferOwnershipSelect.vue';
import CharacterSpecPicker from './Components/Character/CharacterSpecPicker.vue';
import CharactersPage from './Components/Character/CharactersPage.vue';
import OnboardingStepper from './Components/Onboarding/OnboardingStepper.vue';




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
app.component('join-static', JoinStatic);
app.component('logs-index', LogsIndex);
app.component('log-show', LogShow);
app.component('settings-tabs', SettingsTabs);
app.component('settings-logs', SettingsLogs);
app.component('settings-schedule', SettingsSchedule);
app.component('settings-discord', SettingsDiscord);
app.component('settings-profile', SettingsProfile);
app.component('static-setup', StaticSetup);
app.component('consumables-planner', ConsumablesPlanner);
app.component('consumable-card', ConsumableCard);
app.component('transaction-history', TransactionHistory);
app.component('dashboard-view', DashboardView);
app.component('transfer-ownership-select', TransferOwnershipSelect);
app.component('character-spec-picker', CharacterSpecPicker);
app.component('characters-page', CharactersPage);
app.component('onboarding-stepper', OnboardingStepper);

// Mount Vue to the element with id="app" if it exists
if (document.getElementById('app')) {
    app.mount('#app');
}

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();
