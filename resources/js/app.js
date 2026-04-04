import './bootstrap';

import { createApp } from 'vue';
import RosterOverview from './Components/RosterOverview.vue';
import UnifiedRoster from './Components/UnifiedRoster.vue';
import SyncStatusWidget from './Components/SyncStatusWidget.vue';
import TreasuryDashboard from './Components/TreasuryDashboard.vue';
import ScheduleCalendar from './Components/ScheduleCalendar.vue';
import RaidEventDetails from './Components/RaidEventDetails.vue';

const app = createApp({});
app.component('roster-overview', RosterOverview);
app.component('unified-roster', UnifiedRoster);
app.component('sync-status-widget', SyncStatusWidget);
app.component('treasury-dashboard', TreasuryDashboard);
app.component('schedule-calendar', ScheduleCalendar);
app.component('raid-event-details', RaidEventDetails);

// Mount Vue to the element with id="app" if it exists
if (document.getElementById('app')) {
    app.mount('#app');
}

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();
