import './bootstrap';

import { createApp } from 'vue';
import RosterOverview from './Components/Roster/RosterOverview.vue';
import UnifiedRoster from './Components/Roster/UnifiedRoster.vue';
import SyncStatusWidget from './Components/UI/SyncStatusWidget.vue';
import TreasuryDashboard from './Components/Treasury/TreasuryDashboard.vue';
import ScheduleCalendar from './Components/Schedule/ScheduleCalendar.vue';
import EventDetails from './Components/Raid/EventDetails.vue';
import JoinStatic from './Components/Statics/JoinStatic.vue';
import LogsIndex from './Components/Statics/LogsIndex.vue';
import LogShow from './Components/Statics/LogShow.vue';
import SettingsTabs from './Components/Statics/SettingsTabs.vue';
import SettingsLogs from './Components/Statics/SettingsLogs.vue';
import SettingsSchedule from './Components/Statics/SettingsSchedule.vue';
import SettingsDiscord from './Components/Statics/SettingsDiscord.vue';
import StaticSetup from './Components/Statics/StaticSetup.vue';
import ConsumablesPlanner from './Components/Treasury/ConsumablesPlanner.vue';
import ConsumableCard from './Components/Treasury/ConsumableCard.vue';
import DashboardView from './Components/Dashboard/DashboardView.vue';
import TransferOwnershipSelect from './Components/Profile/TransferOwnershipSelect.vue';
import CharacterSpecPicker from './Components/Character/CharacterSpecPicker.vue';
import CharactersPage from './Components/Character/CharactersPage.vue';

const app = createApp({});
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
app.component('static-setup', StaticSetup);
app.component('consumables-planner', ConsumablesPlanner);
app.component('consumable-card', ConsumableCard);
app.component('dashboard-view', DashboardView);
app.component('transfer-ownership-select', TransferOwnershipSelect);
app.component('character-spec-picker', CharacterSpecPicker);
app.component('characters-page', CharactersPage);

// Mount Vue to the element with id="app" if it exists
if (document.getElementById('app')) {
    app.mount('#app');
}

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();
