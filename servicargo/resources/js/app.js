import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import VueApexCharts from 'vue3-apexcharts';
import { useToast } from './servicios/useUI';

// Red de seguridad global: si una petición al backend falla y su promesa no fue
// atrapada con try/catch (p. ej. una carga inicial de datos), el usuario igual ve
// un aviso en español en lugar de un fallo silencioso. Solo actúa sobre los
// errores de nuestro cliente HTTP (tienen .status), no sobre otros errores de JS.
window.addEventListener('unhandledrejection', (evento) => {
    const razon = evento?.reason;
    if (!razon || typeof razon.status === 'undefined') return;
    evento.preventDefault();
    useToast().error(razon.message || 'Ocurrió un error inesperado. Inténtalo de nuevo.');
});

createInertiaApp({
    title: (title) => (title ? `${title} · Servicargo` : 'Servicargo'),
    resolve: (name) => {
        const pages = import.meta.glob('./Vistas/**/*.vue', { eager: true });
        return pages[`./Vistas/${name}.vue`];
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(VueApexCharts)
            .mount(el);
    },
    progress: { color: '#1f6feb' },
});
