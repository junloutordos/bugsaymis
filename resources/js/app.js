import '../css/app.css';
import './bootstrap';
import '@fontsource/inter/index.css'


import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'CRCMIS';
const appUrl   = import.meta.env.VITE_APP_URL;

// If the page loaded on the wrong origin (e.g. 8443 instead of 8080),
// redirect to the correct URL immediately before mounting anything.
if (appUrl && window.location.origin !== appUrl) {
    window.location.replace(appUrl + window.location.pathname + window.location.search + window.location.hash);
}

import { router } from '@inertiajs/vue3';

// Reload page on CSRF expiry (419) so the user gets a fresh token
router.on('invalid', (event) => {
    if (event.detail.response.status === 419) {
        event.preventDefault();
        window.location.reload();
    }
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
