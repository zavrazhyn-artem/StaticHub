import './bootstrap';

import { createApp } from 'vue';
import RosterOverview from './Components/RosterOverview.vue';
import SyncStatusWidget from './Components/SyncStatusWidget.vue';

const app = createApp({});
app.component('roster-overview', RosterOverview);
app.component('sync-status-widget', SyncStatusWidget);

// Mount Vue to the element with id="app" if it exists
if (document.getElementById('app')) {
    app.mount('#app');
}

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();
