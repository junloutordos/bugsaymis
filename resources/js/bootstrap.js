import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

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
