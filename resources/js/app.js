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
import { sessionExpired } from './Composables/useSession';

// 419 = CSRF/session expired; 405 = stale POST URL reloaded as GET after a 419 reload.
// Both mean the session is no longer valid — show the expired overlay instead of
// letting Inertia render the raw error body or doing a blind window.location.reload()
// (which resubmits the last POST and produces the 405 in the first place).
router.on('invalid', (event) => {
    const status = event.detail.response.status;
    if (status === 419 || status === 405) {
        event.preventDefault();
        sessionExpired.value = true;
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
