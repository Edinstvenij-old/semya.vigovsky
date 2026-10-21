import axios from 'axios';

/**
 * Axios instance for the Laravel API. Sanctum SPA auth: session cookie +
 * XSRF-TOKEN cookie, which axios sends back as the X-XSRF-TOKEN header.
 */
export const api = axios.create({
    baseURL: '/api',
    withCredentials: true,
    withXSRFToken: true,
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
});

let csrfReady = false;

export async function ensureCsrf() {
    if (!csrfReady) {
        await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
        csrfReady = true;
    }
}

const listeners = { unauthorized: [], twoFactor: [] };
export function onApi(event, fn) {
    listeners[event].push(fn);
}

api.interceptors.request.use(async (config) => {
    if (config.method !== 'get') await ensureCsrf();
    return config;
});

api.interceptors.response.use(
    (response) => response,
    async (error) => {
        const status = error.response?.status;
        if (status === 419 && !error.config._retried) {
            // CSRF token expired: refresh once and retry.
            csrfReady = false;
            await ensureCsrf();
            error.config._retried = true;
            return api.request(error.config);
        }
        if (status === 401) listeners.unauthorized.forEach((fn) => fn());
        if (status === 423) listeners.twoFactor.forEach((fn) => fn(error.response.data));
        return Promise.reject(error);
    },
);

/** Human-readable message from an API error (first validation error or message). */
export function errorText(error) {
    const data = error?.response?.data;
    if (data?.errors) return Object.values(data.errors)[0]?.[0];
    return data?.message || 'Что-то пошло не так. Проверьте интернет и попробуйте ещё раз.';
}

/** Validation errors as { field: 'message' }. */
export function fieldErrors(error) {
    const errors = error?.response?.data?.errors || {};
    return Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, v[0]]));
}
