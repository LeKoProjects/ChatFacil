import { createApp } from 'vue';

import App from './App.vue';
import type { WidgetConfig } from './types';

const config = (window as unknown as { NEXO_WIDGET: WidgetConfig }).NEXO_WIDGET;

createApp(App, { config }).mount('#nexo-widget');
