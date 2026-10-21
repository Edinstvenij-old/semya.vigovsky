/*
 * Family Hub service worker.
 *
 * 1. Offline mode (blackouts, weak mobile internet abroad): the app shell, built
 *    assets and the last API answers are cached, so the app opens and shows the
 *    latest statuses, tickets and lists without a connection. Answers served from
 *    the cache carry an "X-From-Cache: 1" header so the app can say the data may be old.
 * 2. Web Push: urgent (logistics) notifications vibrate, make a sound and stay on
 *    screen; everything else arrives silently.
 */
const VERSION = 'v2';
const STATIC = `fh-static-${VERSION}`;
const DATA = `fh-data-${VERSION}`;
const SHELL = ['/', '/manifest.webmanifest', '/crest.png', '/icon-192.png', '/favicon-32.png'];
const NETWORK_TIMEOUT_MS = 4000;

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC).then((c) => c.addAll(SHELL)).catch(() => {}).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => ![STATIC, DATA].includes(k)).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

// The app asks to forget cached family data on logout or when another person signs in.
self.addEventListener('message', (event) => {
    if (event.data?.type === 'clear-data') event.waitUntil(caches.delete(DATA));
});

function withTimeout(promise, ms) {
    return Promise.race([promise, new Promise((_, reject) => setTimeout(() => reject(new Error('timeout')), ms))]);
}

async function markedFromCache(response) {
    const headers = new Headers(response.headers);
    headers.set('X-From-Cache', '1');
    return new Response(await response.blob(), { status: response.status, statusText: response.statusText, headers });
}

/** Network first; on failure or slowness answer from the cache. */
async function networkFirst(request, cacheName, { fallbackUrl = null, mark = false } = {}) {
    const cache = await caches.open(cacheName);
    try {
        const response = await withTimeout(fetch(request), NETWORK_TIMEOUT_MS);
        if (response.ok) cache.put(fallbackUrl || request, response.clone());
        return response;
    } catch (error) {
        const cached = await cache.match(fallbackUrl || request) || (fallbackUrl && await caches.match(fallbackUrl));
        if (cached) return mark ? markedFromCache(cached) : cached;
        throw error;
    }
}

/** Hashed build files and icons never change: cache first. */
async function cacheFirst(request) {
    const cached = await caches.match(request);
    if (cached) return cached;
    const response = await fetch(request);
    if (response.ok) (await caches.open(STATIC)).put(request, response.clone());
    return response;
}

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);
    if (request.method !== 'GET' || url.origin !== self.location.origin) return;

    if (request.mode === 'navigate') {
        // Every SPA route is the same HTML shell.
        event.respondWith(networkFirst(request, STATIC, { fallbackUrl: '/' }));
    } else if (url.pathname.startsWith('/build/') || /\.(png|ico|svg|webmanifest)$/.test(url.pathname)) {
        event.respondWith(cacheFirst(request));
    } else if (url.pathname.startsWith('/api/') && !url.pathname.startsWith('/api/family/backups')) {
        event.respondWith(networkFirst(request, DATA, { mark: true }));
    }
});

self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch {
        data = { title: event.data?.text() };
    }

    const urgent = !!data.urgent;
    const options = {
        body: data.body || '',
        icon: '/icon-192.png',
        tag: urgent ? `urgent-${data.id}` : data.channel || 'family-hub',
        renotify: urgent,
        silent: !urgent,
        requireInteraction: urgent,
        vibrate: urgent ? [300, 100, 300, 100, 600] : undefined,
        data: { url: data.url || '/' },
    };

    event.waitUntil(self.registration.showNotification(data.title || 'Family Hub', options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            for (const client of clients) {
                if (new URL(client.url).origin === self.location.origin) {
                    client.navigate(url);
                    return client.focus();
                }
            }
            return self.clients.openWindow(url);
        }),
    );
});
