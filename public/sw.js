/*
 * Family Hub service worker: Web Push.
 * Urgent (logistics) notifications vibrate, make a sound and stay on screen;
 * everything else arrives silently.
 */
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

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
        icon: '/icon.svg',
        badge: '/icon.svg',
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
