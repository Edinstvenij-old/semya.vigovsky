import { createRouter, createWebHistory } from 'vue-router';
import { store } from './store';

const routes = [
    { path: '/login', component: () => import('./pages/Login.vue'), meta: { guest: true, title: 'Вход' } },
    { path: '/register', component: () => import('./pages/Register.vue'), meta: { guest: true, title: 'Новая семья' } },
    { path: '/forgot-password', component: () => import('./pages/ForgotPassword.vue'), meta: { guest: true, title: 'Забыли пароль' } },
    { path: '/reset-password/:token', component: () => import('./pages/ResetPassword.vue'), meta: { bare: true, public: true, title: 'Новый пароль' } },
    { path: '/invite/:token', component: () => import('./pages/Invite.vue'), meta: { bare: true, public: true, title: 'Приглашение' } },

    { path: '/', component: () => import('./pages/Dashboard.vue'), meta: { title: 'Главная' } },
    { path: '/tasks', component: () => import('./pages/Tasks.vue'), meta: { title: 'Задачи' } },
    { path: '/tasks/:id', component: () => import('./pages/TaskDetail.vue'), meta: { title: 'Задача' } },
    { path: '/home', component: () => import('./pages/HomeMaintenance.vue'), meta: { title: 'Дом и обслуживание' } },
    { path: '/travel', component: () => import('./pages/Travel.vue'), meta: { title: 'Поездки' } },
    { path: '/documents', component: () => import('./pages/Documents.vue'), meta: { title: 'Билеты и брони' } },
    { path: '/threads', component: () => import('./pages/Threads.vue'), meta: { title: 'Чаты' } },
    { path: '/threads/:id', component: () => import('./pages/ThreadView.vue'), meta: { title: 'Обсуждение' } },
    { path: '/notifications', component: () => import('./pages/Notifications.vue'), meta: { title: 'Уведомления' } },
    { path: '/family', component: () => import('./pages/Family.vue'), meta: { title: 'Семья' } },
    { path: '/settings', component: () => import('./pages/Settings.vue'), meta: { title: 'Настройки' } },
    { path: '/:pathMatch(.*)*', redirect: '/' },
];

export const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: () => ({ top: 0 }),
});

router.beforeEach((to) => {
    if (to.meta.public) return true;
    if (to.meta.guest) return store.user ? '/' : true;
    if (!store.user) return { path: '/login', query: to.fullPath !== '/' ? { redirect: to.fullPath } : {} };
    return true;
});

router.afterEach((to) => {
    document.title = `${to.meta.title || ''} · ${store.config.app_name}`;
});
