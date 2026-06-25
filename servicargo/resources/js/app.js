import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';

createInertiaApp({
    title: (title) => (title ? `${title} · Servicargo` : 'Servicargo'),
    resolve: (name) => {
        const pages = import.meta.glob('./Vistas/**/*.vue', { eager: true });
        return pages[`./Vistas/${name}.vue`];
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: { color: '#1f6feb' },
});
