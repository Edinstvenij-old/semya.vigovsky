<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api } from '../api';
import { simpleMode, stopPolling, store } from '../store';
import Avatar from './Avatar.vue';
import Icon from './Icon.vue';

const router = useRouter();
const route = useRoute();
const menuOpen = ref(false);
const menuPanel = ref(null);

const nav = computed(() => [
    { to: '/', label: 'Главная', icon: 'home', exact: true },
    { to: '/tasks', label: 'Задачи', icon: 'list-checks', hidden: simpleMode() },
    { to: '/home', label: 'Дом', icon: 'calendar-days' },
    { to: '/travel', label: 'Поездки', icon: 'plane' },
    { to: '/threads', label: 'Чаты', icon: 'message-circle', badge: store.counts.unread_messages },
].filter((i) => !i.hidden));

const secondary = computed(() => [
    { to: '/notifications', label: 'Уведомления', icon: 'bell', badge: store.counts.unread_notifications },
    { to: '/shopping', label: 'Список покупок', icon: 'shopping-cart', badge: store.counts.shopping_open },
    { to: '/documents', label: 'Билеты и брони', icon: 'lock' },
    { to: '/family', label: 'Семья', icon: 'users' },
    { to: '/settings', label: 'Настройки', icon: 'settings' },
]);

// Mobile burger menu: secondary pages live here so the top bar never crowds the family name.
async function openMenu() {
    menuOpen.value = true;
    document.body.style.overflow = 'hidden';
    await nextTick();
    menuPanel.value?.querySelector('a, button')?.focus();
}

function closeMenu() {
    menuOpen.value = false;
    document.body.style.overflow = '';
}

function onKey(e) {
    if (e.key === 'Escape' && menuOpen.value) closeMenu();
}

watch(() => route.fullPath, closeMenu);
document.addEventListener('keydown', onKey);
onBeforeUnmount(() => document.removeEventListener('keydown', onKey));

async function logout() {
    closeMenu();
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
            <header class="sticky top-0 z-30 flex items-center gap-2 bg-brand-900 px-3 py-2 text-white md:hidden">
                <!-- Capped sizes: with the largest text setting the family name must still fit next to the burger. -->
                <RouterLink to="/" class="flex min-w-0 flex-1 items-center gap-2 font-bold" :style="{ fontSize: 'min(1.25rem, 6vw)' }">
                    <img :src="'/crest.png'" alt="" class="h-[44px] w-auto shrink-0">
                    <span class="truncate">{{ store.user?.family?.name || store.config.app_name }}</span>
                </RouterLink>
                <button class="relative flex min-h-12 min-w-12 shrink-0 items-center justify-center rounded-xl hover:bg-white/10"
                        :aria-expanded="menuOpen" aria-controls="mobile-menu" aria-label="Меню" @click="openMenu">
                    <Icon name="menu" size="1.6em" />
                    <span v-if="store.counts.unread_notifications" class="absolute right-1 top-1 h-3 w-3 rounded-full bg-amber-400 ring-2 ring-brand-900">
                        <span class="sr-only">есть непрочитанные уведомления</span>
                    </span>
                </button>
            </header>

            <!-- Mobile burger menu -->
            <div v-if="menuOpen" class="fixed inset-0 z-50 flex justify-end bg-black/50 md:hidden" @click.self="closeMenu">
                <nav id="mobile-menu" ref="menuPanel" class="flex h-full w-full max-w-sm flex-col overflow-y-auto bg-white p-4 shadow-2xl"
                     :style="{ fontSize: 'min(1.125rem, 6vw)', overflowWrap: 'normal' }" aria-label="Дополнительное меню">
                    <div class="mb-4 flex items-center gap-3">
                        <Avatar :user="store.user" />
                        <div class="min-w-0 flex-1">
                            <div class="font-bold leading-tight">{{ store.user?.name }}</div>
                            <div class="text-slate-600" style="font-size: 0.85em">{{ store.user?.role_label }} · {{ store.user?.family?.name }}</div>
                        </div>
                        <button class="btn-ghost shrink-0 px-3" aria-label="Закрыть меню" @click="closeMenu"><Icon name="x" /></button>
                    </div>
                    <RouterLink v-for="item in secondary" :key="item.to" :to="item.to"
                                class="flex min-h-14 items-center gap-3 rounded-xl px-3 py-2 font-semibold leading-tight text-slate-800 hover:bg-slate-100"
                                active-class="!bg-brand-50 !text-brand-800">
                        <Icon :name="item.icon" />
                        <span class="min-w-0 flex-1">{{ item.label }}</span>
                        <span v-if="item.badge" class="shrink-0 rounded-full bg-amber-400 px-2 text-sm font-bold text-black">{{ item.badge }}</span>
                    </RouterLink>
                    <button class="mt-auto flex min-h-14 items-center gap-3 rounded-xl px-3 font-semibold text-red-800 hover:bg-red-50" @click="logout">
                        <Icon name="log-out" /> Выйти
                    </button>
                </nav>
            </div>

            <!-- Unanswered "is everyone OK?" roll call: visible on every page until answered -->
            <RouterLink v-if="store.counts.safety_pending && route.path !== '/'" to="/" role="alert"
                        class="sticky top-16 z-20 m-3 flex items-center gap-3 rounded-2xl bg-red-700 p-4 text-white shadow-lg md:top-3">
                <Icon name="shield" size="1.75em" />
                <span class="flex-1"><b class="block text-lg">Все ли в порядке?</b>Семья ждёт вашего ответа — нажмите здесь</span>
            </RouterLink>

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
                    class="relative flex min-h-16 min-w-0 flex-col items-center justify-center gap-0.5 px-0.5 font-semibold text-slate-600"
                    :style="{ fontSize: 'min(0.875rem, 3.8vw)' /* labels stay on one line even with the largest text size */ }"
                    :active-class="item.exact ? '' : '!text-brand-700'"
                    exact-active-class="!text-brand-700"
                >
                    <Icon :name="item.icon" size="1.8em" />
                    <span class="max-w-full truncate">{{ item.label }}</span>
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
