import { reactive } from 'vue';
import { api, flushOutbox, setOutboxUser } from './api';
import { getLocale, setLocale } from './i18n';
import { connectRealtime, disconnectRealtime } from './realtime';

/** Tiny global store: session user, app config, badge counters, toasts. */
export const store = reactive({
    user: null,
    config: { app_name: 'Family Hub', registration_open: false, vapid_public_key: null },
    counts: { unread_messages: 0, unread_notifications: 0, latest_urgent: null },
    members: [],
    toasts: [],
    urgentBanner: null,
    loaded: false,
});

export async function loadSession() {
    const [config, me] = await Promise.all([api.get('/config'), api.get('/me')]);
    store.config = config.data;
    setUser(me.data.user);
    store.loaded = true;
}

export function setUser(user) {
    // The language saved in the profile wins over the device language (reloads once if different).
    const saved = user?.preferences?.locale;
    if (saved && saved !== getLocale()) setLocale(saved);
    store.user = user;
    applyPreferences();
    setOutboxUser(user?.id ?? null);
    if (user) {
        forgetOtherUsersCache(user.id);
        flushOutbox();
    }
}

/** Cached API answers belong to one person: drop them when someone else signs in on this device. */
export function clearOfflineCache() {
    navigator.serviceWorker?.controller?.postMessage({ type: 'clear-data' });
}

function forgetOtherUsersCache(userId) {
    try {
        const last = localStorage.getItem('fh-last-user');
        if (last && Number(last) !== userId) clearOfflineCache();
        localStorage.setItem('fh-last-user', String(userId));
    } catch {
        /* storage unavailable */
    }
}

/** Applies accessibility preferences to the document root. */
export function applyPreferences() {
    const p = store.user?.preferences || { font_scale: 1.25 };
    document.documentElement.style.fontSize = `${16 * (p.font_scale || 1)}px`;
    document.documentElement.classList.toggle('high-contrast', !!p.high_contrast);
}

export function simpleMode() {
    return store.user && (!store.user.can_write || store.user.preferences?.simple_mode);
}

export async function loadMembers(force = false) {
    if (store.members.length && !force) return store.members;
    const { data } = await api.get('/family/members');
    store.members = data.members;
    return store.members;
}

let lastUrgentId = null;
let timer = null;

export async function refreshCounts() {
    if (!store.user) return;
    try {
        const { data } = await api.get('/counts');
        store.counts = data;
        const urgent = data.latest_urgent;
        if (urgent && lastUrgentId !== null && urgent.id !== lastUrgentId) {
            store.urgentBanner = urgent;
            beep();
        }
        lastUrgentId = urgent?.id ?? 0;
    } catch {
        /* offline — try again next tick */
    }
}

export function startPolling() {
    stopPolling();
    connectRealtime(store.config.realtime, store.user?.family?.id, refreshCounts);
    refreshCounts();
    timer = setInterval(refreshCounts, 20000);
}

export function stopPolling() {
    disconnectRealtime();
    clearInterval(timer);
    lastUrgentId = null;
}

export function toast(message, type = 'success') {
    const id = Math.random();
    store.toasts.push({ id, message, type });
    setTimeout(() => (store.toasts = store.toasts.filter((t) => t.id !== id)), 4000);
}

/** Short two-tone chime for urgent statuses while the app is open. */
function beep() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        [880, 660].forEach((freq, i) => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.frequency.value = freq;
            gain.gain.value = 0.15;
            osc.connect(gain).connect(ctx.destination);
            osc.start(ctx.currentTime + i * 0.25);
            osc.stop(ctx.currentTime + i * 0.25 + 0.2);
        });
    } catch {
        /* audio blocked until the user interacts — fine */
    }
    navigator.vibrate?.([200, 100, 200]);
}
