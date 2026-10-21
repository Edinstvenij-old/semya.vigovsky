import '../css/app.css';
import { createApp } from 'vue';
import App from './App.vue';
import { router } from './router';
import { onApi } from './api';
import { loadSession, startPolling, stopPolling, store } from './store';

onApi('unauthorized', () => {
    if (store.user) {
        store.user = null;
        stopPolling();
        router.push({ path: '/login', query: { redirect: router.currentRoute.value.fullPath } });
    }
});

loadSession()
    .catch(() => (store.loaded = true))
    .finally(() => {
        if (store.user) startPolling();
        createApp(App).use(router).mount('#app');
    });

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(() => {});
}
