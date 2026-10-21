import axios from 'axios';
import { reactive } from 'vue';
import { getLocale } from './i18n';
import { t } from './i18n';

/**
 * Axios instance for the Laravel API. Sanctum SPA auth: session cookie +
 * XSRF-TOKEN cookie, which axios sends back as the X-XSRF-TOKEN header.
 */
export const api = axios.create({
    baseURL: '/api',
    withCredentials: true,
    withXSRFToken: true,
    // X-Locale: language for server messages until the user has a saved preference.
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-Locale': getLocale() },
});

/** Connection state shown by the layout: offline, data from cache, queued requests. */
export const net = reactive({ offline: !navigator.onLine, stale: false, queued: 0 });

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

// --- Outbox: important actions made without a connection are sent later -------
//
// Statuses, "I'm safe" answers, text messages and shopping-list changes are
// stored in localStorage when the network is down and replayed in order once it
// is back. Photos are not queued (too big for local storage).
const OUTBOX_KEY = 'fh-outbox';
const QUEUEABLE = [
    /^\/checkins$/,
    /^\/safety-checks\/\d+\/respond$/,
    /^\/threads\/\d+\/messages$/,
    /^\/shopping(\/\d+)?$/,
];
let currentUserId = null;

export function setOutboxUser(id) {
    currentUserId = id;
    net.queued = readOutbox().filter((r) => r.user === id).length;
}

function readOutbox() {
    try {
        return JSON.parse(localStorage.getItem(OUTBOX_KEY) || '[]');
    } catch {
        return [];
    }
}

function writeOutbox(items) {
    try {
        localStorage.setItem(OUTBOX_KEY, JSON.stringify(items));
    } catch {
        /* storage blocked: nothing we can do, the request is lost */
    }
    net.queued = items.filter((r) => r.user === currentUserId).length;
}

export function clearOutbox() {
    writeOutbox([]);
}

function queueable(config) {
    return ['post', 'patch'].includes(config.method)
        && !(config.data instanceof FormData)
        && QUEUEABLE.some((re) => re.test(config.url));
}

let flushing = false;
export async function flushOutbox() {
    if (flushing || !currentUserId || !navigator.onLine) return;
    flushing = true;
    try {
        for (const item of readOutbox()) {
            if (item.user !== currentUserId) continue;
            try {
                await api.request({ method: item.method, url: item.url, data: item.data, _fromOutbox: true });
            } catch (e) {
                if (!e.response) break; // still offline: keep the rest for later
                // Rejected by the server (e.g. roll call closed meanwhile): drop it.
            }
            writeOutbox(readOutbox().filter((r) => r.id !== item.id));
        }
    } finally {
        flushing = false;
    }
}

window.addEventListener('online', () => {
    net.offline = false;
    flushOutbox();
});
window.addEventListener('offline', () => (net.offline = true));
setInterval(flushOutbox, 30000);

api.interceptors.request.use(async (config) => {
    if (config.method !== 'get') {
        try {
            await ensureCsrf();
        } catch {
            /* offline: the request fails below and may be queued */
        }
    }
    return config;
});

api.interceptors.response.use(
    (response) => {
        if (response.config.method === 'get') {
            net.stale = response.headers['x-from-cache'] === '1';
            if (!net.stale) net.offline = false;
        }
        return response;
    },
    async (error) => {
        const config = error.config || {};
        const status = error.response?.status;

        if (!error.response && queueable(config) && !config._fromOutbox && currentUserId) {
            net.offline = true;
            const data = typeof config.data === 'string' ? JSON.parse(config.data || '{}') : config.data;
            const url = config.url.replace(/^\/api/, '');
            writeOutbox([...readOutbox(), { id: Date.now() + Math.random(), user: currentUserId, method: config.method, url, data }]);
            return { data: { queued: true }, status: 202, config, headers: {} };
        }

        if (status === 419 && !config._retried) {
            // CSRF token expired: refresh once and retry.
            csrfReady = false;
            await ensureCsrf();
            config._retried = true;
            return api.request(config);
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
    if (!error?.response) return t('Нет связи с сервером. Проверьте интернет и попробуйте ещё раз.');
    return data?.message || t('Что-то пошло не так. Попробуйте ещё раз.');
}

/** Validation errors as { field: 'message' }. */
export function fieldErrors(error) {
    const errors = error?.response?.data?.errors || {};
    return Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, v[0]]));
}
