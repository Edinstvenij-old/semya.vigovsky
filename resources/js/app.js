import '../css/app.css';
import { createApp } from 'vue';
import App from './App.vue';
import { router } from './router';
import { onApi } from './api';
import { t } from './i18n';
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
        const app = createApp(App).use(router);
        app.config.globalProperties.$t = t;
        app.mount('#app');
    });

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(() => {});
}
