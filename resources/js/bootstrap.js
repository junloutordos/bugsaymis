import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// ── Laravel Echo + Soketi (Pusher-compatible WebSocket) ───────────────────
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster:       'pusher',
    key:               import.meta.env.VITE_PUSHER_APP_KEY || 'bugsaymis-app-key',
    wsHost:            import.meta.env.VITE_PUSHER_HOST       ?? 'localhost',
    wsPort:            Number(import.meta.env.VITE_PUSHER_PORT ?? 9601),
    wssPort:           Number(import.meta.env.VITE_PUSHER_PORT ?? 9601),
    httpHost:          import.meta.env.VITE_PUSHER_HOST       ?? 'localhost',
    httpPort:          Number(import.meta.env.VITE_PUSHER_PORT ?? 9601),
    cluster:           import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
    forceTLS:          (import.meta.env.VITE_PUSHER_SCHEME ?? 'http') === 'https',
    // WebSocket only — Soketi does not implement the Pusher HTTP fallback
    // transports (xhr_streaming/xhr_polling); enabling them makes clients that
    // fail the ws upgrade loop forever on /pusher/* endpoints that 502.
    enabledTransports: ['ws', 'wss'],
    disableStats:      true,
});

// Interceptor: capture server error responses for vehicle-requests and show raw body in console
window.axios.interceptors.response.use(
	response => response,
	error => {
		try {
			const req = error.config?.url || '';
			if (req.includes('/vehicle-requests')) {
				console.error('Server response for', req, error.response?.status, error.response?.data);
				// expose lastVehicleRequestError globally for UI to read if necessary
				window.lastVehicleRequestError = {
					status: error.response?.status,
					data: error.response?.data,
				};
			}
		} catch (e) {
			console.error('Error in axios interceptor', e);
		}
		return Promise.reject(error);
	}
);
