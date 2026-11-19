import '@fontsource-variable/inter';
import '@fontsource-variable/montserrat';
import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';

createInertiaApp({
    title: (title) => (title ? `${title} · NEXO Chat` : 'NEXO Chat'),
    resolve: (name) => {
        const pages = import.meta.glob<DefineComponent>('./pages/**/*.vue', { eager: true });
        const page = pages[`./pages/${name}.vue`];
        if (!page) {
            throw new Error(`Página não encontrada: ${name}`);
        }
        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: { color: '#3BA0FF' },
});
