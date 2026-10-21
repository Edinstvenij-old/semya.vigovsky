<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '../api';
import { simpleMode, stopPolling, store } from '../store';
import Avatar from './Avatar.vue';
import Icon from './Icon.vue';

const router = useRouter();

const nav = computed(() => [
    { to: '/', label: 'Главная', icon: 'home', exact: true },
    { to: '/tasks', label: 'Задачи', icon: 'list-checks', hidden: simpleMode() },
    { to: '/home', label: 'Дом', icon: 'calendar-days' },
    { to: '/travel', label: 'Поездки', icon: 'plane' },
    { to: '/threads', label: 'Чаты', icon: 'message-circle', badge: store.counts.unread_messages },
].filter((i) => !i.hidden));

const secondary = computed(() => [
    { to: '/notifications', label: 'Уведомления', icon: 'bell', badge: store.counts.unread_notifications },
    { to: '/family', label: 'Семья', icon: 'users' },
    { to: '/settings', label: 'Настройки', icon: 'settings' },
]);

async function logout() {
    await api.post('/logout');
    stopPolling();
    store.user = null;
    router.push('/login');
}

function openUrgent() {
    const { url, id } = store.urgentBanner;
    store.urgentBanner = null;
    api.post('/notifications/read', { ids: [id] }).catch(() => {});
    router.push(url);
}
</script>

<template>
    <div class="min-h-screen md:flex">
        <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-2 focus:top-2 focus:z-50 focus:rounded-lg focus:bg-white focus:p-3">
            Перейти к содержимому
        </a>

        <!-- Desktop sidebar -->
        <aside class="sticky top-0 hidden h-screen w-72 shrink-0 flex-col bg-brand-900 p-4 text-white md:flex">
            <RouterLink to="/" class="mb-6 flex items-center gap-3 px-2">
                <img :src="'/crest.png'" alt="" class="h-14 w-auto shrink-0 drop-shadow">
                <span>
                    <span class="block text-lg font-bold leading-tight">{{ store.config.app_name }}</span>
                    <span class="block text-sm text-red-200">{{ store.user?.family?.name }}</span>
                </span>
            </RouterLink>
            <nav class="flex flex-1 flex-col gap-1" aria-label="Основное меню">
                <RouterLink
                    v-for="item in [...nav, ...secondary]"
                    :key="item.to"
                    :to="item.to"
                    class="flex min-h-12 items-center gap-3 rounded-xl px-3 text-lg font-semibold text-red-100 hover:bg-white/10"
                    :active-class="item.exact ? '' : '!bg-white !text-brand-900'"
                    :exact-active-class="'!bg-white !text-brand-900'"
                >
                    <Icon :name="item.icon" />
                    <span class="flex-1">{{ item.label }}</span>
                    <span v-if="item.badge" class="rounded-full bg-amber-400 px-2 text-sm font-bold text-black">{{ item.badge }}</span>
                </RouterLink>
            </nav>
            <div class="mt-4 flex items-center gap-3 rounded-xl bg-white/10 p-3">
                <Avatar :user="store.user" />
                <div class="min-w-0 flex-1">
                    <div class="truncate font-semibold">{{ store.user?.name }}</div>
                    <div class="text-sm text-red-200">{{ store.user?.role_label }}</div>
                </div>
                <button class="rounded-lg p-2 hover:bg-white/10" aria-label="Выйти" title="Выйти" @click="logout">
                    <Icon name="log-out" />
                </button>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <!-- Mobile top bar -->
            <header class="sticky top-0 z-30 flex items-center justify-between bg-brand-900 px-4 py-2 text-white md:hidden">
                <RouterLink to="/" class="flex items-center gap-2 font-bold">
                    <img :src="'/crest.png'" alt="" class="h-9 w-auto"> {{ store.user?.family?.name || store.config.app_name }}
                </RouterLink>
                <div class="flex items-center gap-1">
                    <RouterLink v-for="item in secondary" :key="item.to" :to="item.to" class="relative rounded-xl p-3" :aria-label="item.label">
                        <Icon :name="item.icon" />
                        <span v-if="item.badge" class="absolute right-1 top-1 rounded-full bg-amber-400 px-1.5 text-xs font-bold text-black">{{ item.badge }}</span>
                    </RouterLink>
                </div>
            </header>

            <!-- Urgent status banner (in-app, while the app is open) -->
            <div v-if="store.urgentBanner" role="alert" class="sticky top-14 z-20 m-3 flex items-center gap-3 rounded-2xl bg-amber-400 p-4 text-black shadow-lg md:top-3">
                <Icon name="bell-ring" size="1.75em" />
                <button class="flex-1 text-left" @click="openUrgent">
                    <span class="block text-lg font-bold">{{ store.urgentBanner.title }}</span>
                    <span v-if="store.urgentBanner.body" class="block">{{ store.urgentBanner.body }}</span>
                </button>
                <button class="rounded-lg p-2 hover:bg-black/10" aria-label="Скрыть" @click="store.urgentBanner = null"><Icon name="x" /></button>
            </div>

            <main id="main" class="mx-auto w-full max-w-6xl flex-1 px-4 pb-28 pt-4 md:px-8 md:pb-10 md:pt-8">
                <slot />
            </main>

            <!-- Mobile bottom navigation -->
            <nav class="fixed inset-x-0 bottom-0 z-30 grid border-t-2 border-slate-200 bg-white pb-[env(safe-area-inset-bottom)] md:hidden"
                 :style="{ gridTemplateColumns: `repeat(${nav.length}, minmax(0, 1fr))` }"
                 aria-label="Основное меню">
                <RouterLink
                    v-for="item in nav"
                    :key="item.to"
                    :to="item.to"
                    class="relative flex min-h-16 flex-col items-center justify-center gap-0.5 text-sm font-semibold text-slate-600"
                    :active-class="item.exact ? '' : '!text-brand-700'"
                    exact-active-class="!text-brand-700"
                >
                    <Icon :name="item.icon" size="1.6em" />
                    {{ item.label }}
                    <span v-if="item.badge" class="absolute right-[18%] top-1 rounded-full bg-red-600 px-1.5 text-xs font-bold text-white">{{ item.badge }}</span>
                </RouterLink>
            </nav>
        </div>

        <!-- Toasts -->
        <div class="fixed inset-x-0 bottom-20 z-50 flex flex-col items-center gap-2 px-4 md:bottom-6" aria-live="polite">
            <div v-for="t in store.toasts" :key="t.id"
                 class="rounded-xl px-4 py-3 font-semibold text-white shadow-lg"
                 :class="t.type === 'error' ? 'bg-red-700' : 'bg-slate-900'">
                {{ t.message }}
            </div>
        </div>
    </div>
</template>
