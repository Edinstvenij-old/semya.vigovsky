<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api, clearOutbox, net } from '../api';
import { clearOfflineCache, simpleMode, stopPolling, store } from '../store';
import Avatar from './Avatar.vue';
import Icon from './Icon.vue';
import { t } from '../i18n';

const router = useRouter();
const route = useRoute();
const menuOpen = ref(false);
const menuPanel = ref(null);

const nav = computed(() => [
    { to: '/', label: t('Главная'), icon: 'home', exact: true },
    { to: '/tasks', label: t('Задачи'), icon: 'list-checks', hidden: simpleMode() },
    { to: '/home', label: t('Дом'), icon: 'calendar-days' },
    { to: '/travel', label: t('Поездки'), icon: 'plane' },
    { to: '/threads', label: t('Чаты'), icon: 'message-circle', badge: store.counts.unread_messages },
].filter((i) => !i.hidden));

const secondary = computed(() => [
    { to: '/notifications', label: t('Уведомления'), icon: 'bell', badge: store.counts.unread_notifications },
    { to: '/photos', label: t('Фотоальбом'), icon: 'image' },
    { to: '/shopping', label: t('Покупки'), icon: 'shopping-cart', badge: store.counts.shopping_open },
    { to: '/documents', label: t('Билеты и брони'), icon: 'lock' },
    { to: '/family', label: t('Семья'), icon: 'users' },
    { to: '/settings', label: t('Настройки'), icon: 'settings' },
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
    if (net.queued && !confirm(t('Есть неотправленное (:p0). Если выйти сейчас, оно потеряется. Выйти?', { p0: net.queued }))) return;
    closeMenu();
    clearOutbox();
    clearOfflineCache();
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
            {{ $t('Перейти к содержимому') }}
        </a>

        <!-- Desktop sidebar -->
        <!-- Width in px, not rem: with large text the menu must not eat the content area on laptops. -->
        <aside class="sticky top-0 hidden h-screen w-[clamp(240px,22vw,300px)] shrink-0 flex-col overflow-y-auto bg-brand-900 p-[0.8em] text-white [scrollbar-width:thin] md:flex"
               :style="{ fontSize: 'min(1rem, 18px)' }">
            <!-- Sizes in em: everything inside follows the capped menu font, not the global text size. -->
            <RouterLink to="/" class="mb-[1.2em] flex items-center gap-[0.6em] px-[0.4em]">
                <img :src="'/crest.png'" alt="" class="h-[3.2em] w-auto shrink-0 drop-shadow">
                <span class="min-w-0">
                    <span class="block break-words text-[1.15em] font-bold leading-tight">{{ store.config.app_name }}</span>
                    <span class="block break-words text-[0.85em] text-red-200">{{ store.user?.family?.name }}</span>
                </span>
            </RouterLink>
            <nav class="flex flex-1 flex-col gap-1" :aria-label="$t('Основное меню')">
                <RouterLink
                    v-for="item in [...nav, ...secondary]"
                    :key="item.to"
                    :to="item.to"
                    class="flex min-h-12 items-center gap-[0.5em] rounded-xl px-[0.5em] text-[1em] font-semibold text-red-100 hover:bg-white/10"
                    :active-class="item.exact ? '' : '!bg-white !text-brand-900'"
                    :exact-active-class="'!bg-white !text-brand-900'"
                >
                    <Icon :name="item.icon" />
                    <span class="min-w-0 flex-1 break-words leading-tight">{{ item.label }}</span>
                    <span v-if="item.badge" class="shrink-0 rounded-full bg-amber-400 px-[0.45em] text-[0.8em] font-bold text-black">{{ item.badge }}</span>
                </RouterLink>
            </nav>
            <!-- Full name and role always visible: they wrap instead of being cut; "Выйти" gets its own line. -->
            <div class="mt-[0.8em] rounded-xl bg-white/10 p-[0.6em]">
                <div class="flex items-center gap-[0.6em]">
                    <Avatar :user="store.user" class="!h-[2.4em] !w-[2.4em] !text-[0.95em]" />
                    <div class="min-w-0 flex-1 leading-tight">
                        <div class="break-words font-semibold">{{ store.user?.name }}</div>
                        <div class="mt-0.5 break-words text-[0.85em] text-red-200">{{ store.user?.role_label }}</div>
                    </div>
                </div>
                <button class="mt-2 flex min-h-11 w-full items-center justify-center gap-2 rounded-lg font-semibold hover:bg-white/10" @click="logout">
                    <Icon name="log-out" /> {{ $t('Выйти') }}
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
                        :aria-expanded="menuOpen" aria-controls="mobile-menu" :aria-label="$t('Меню')" @click="openMenu">
                    <Icon name="menu" size="1.6em" />
                    <span v-if="store.counts.unread_notifications" class="absolute right-1 top-1 h-3 w-3 rounded-full bg-amber-400 ring-2 ring-brand-900">
                        <span class="sr-only">{{ $t('есть непрочитанные уведомления') }}</span>
                    </span>
                </button>
            </header>

            <!-- Mobile burger menu -->
            <div v-if="menuOpen" class="fixed inset-0 z-50 flex justify-end bg-black/50 md:hidden" @click.self="closeMenu">
                <!-- Sizes in em: the menu font is capped, so avatar and buttons must not grow with the global text size. -->
                <nav id="mobile-menu" ref="menuPanel" class="flex h-full w-full max-w-sm flex-col overflow-y-auto bg-white p-[0.8em] shadow-2xl"
                     :style="{ fontSize: 'min(1.125rem, 6vw)', overflowWrap: 'normal' }" :aria-label="$t('Дополнительное меню')">
                    <div class="mb-[0.8em] flex items-start gap-[0.6em]">
                        <Avatar :user="store.user" class="!h-[2.4em] !w-[2.4em] !text-[0.95em]" />
                        <div class="min-w-0 flex-1">
                            <div class="font-bold leading-tight">{{ store.user?.name }}</div>
                            <div class="text-slate-600" style="font-size: 0.85em">{{ store.user?.role_label }}</div>
                            <div class="text-slate-600" style="font-size: 0.85em">{{ store.user?.family?.name }}</div>
                        </div>
                        <button class="btn-ghost !min-h-[2.4em] shrink-0 !px-[0.5em]" :aria-label="$t('Закрыть меню')" @click="closeMenu"><Icon name="x" /></button>
                    </div>
                    <RouterLink v-for="item in secondary" :key="item.to" :to="item.to"
                                class="flex min-h-14 items-center gap-3 rounded-xl px-3 py-2 font-semibold leading-tight text-slate-800 hover:bg-slate-100"
                                active-class="!bg-brand-50 !text-brand-800">
                        <Icon :name="item.icon" />
                        <span class="min-w-0 flex-1">{{ item.label }}</span>
                        <span v-if="item.badge" class="shrink-0 rounded-full bg-amber-400 px-2 text-sm font-bold text-black">{{ item.badge }}</span>
                    </RouterLink>
                    <button class="mt-auto flex min-h-14 items-center gap-3 rounded-xl px-3 font-semibold text-red-800 hover:bg-red-50" @click="logout">
                        <Icon name="log-out" /> {{ $t('Выйти') }}
                    </button>
                </nav>
            </div>

            <!-- Offline / cached data / queued actions -->
            <div v-if="net.offline || net.stale || net.queued" role="status"
                 class="flex items-center gap-2 bg-slate-800 px-4 py-2 text-sm font-semibold text-white">
                <Icon name="refresh-cw" />
                <span class="flex-1">
                    <template v-if="net.offline || net.stale">{{ $t('Нет связи — показаны сохранённые данные.') }}</template>
                    <template v-if="net.queued"> {{ $t('Ждут отправки: :p0. Отправим сами, когда появится интернет.', { p0: net.queued }) }}</template>
                </span>
            </div>

            <!-- Unanswered "is everyone OK?" roll call: visible on every page until answered -->
            <RouterLink v-if="store.counts.safety_pending && route.path !== '/'" to="/" role="alert"
                        class="sticky top-16 z-20 m-3 flex items-center gap-3 rounded-2xl bg-red-700 p-4 text-white shadow-lg md:top-3">
                <Icon name="shield" size="1.75em" />
                <span class="flex-1"><b class="block text-lg">{{ $t('Все ли в порядке?') }}</b>{{ $t('Семья ждёт вашего ответа — нажмите здесь') }}</span>
            </RouterLink>

            <!-- Urgent status banner (in-app, while the app is open) -->
            <div v-if="store.urgentBanner" role="alert" class="sticky top-14 z-20 m-3 flex items-center gap-3 rounded-2xl bg-amber-400 p-4 text-black shadow-lg md:top-3">
                <Icon name="bell-ring" size="1.75em" />
                <button class="flex-1 text-left" @click="openUrgent">
                    <span class="block text-lg font-bold">{{ store.urgentBanner.title }}</span>
                    <span v-if="store.urgentBanner.body" class="block">{{ store.urgentBanner.body }}</span>
                </button>
                <button class="rounded-lg p-2 hover:bg-black/10" :aria-label="$t('Скрыть')" @click="store.urgentBanner = null"><Icon name="x" /></button>
            </div>

            <main id="main" class="mx-auto w-full max-w-6xl flex-1 px-4 pb-28 pt-4 md:px-8 md:pb-10 md:pt-8">
                <slot />
            </main>

            <!-- Mobile bottom navigation -->
            <nav class="fixed inset-x-0 bottom-0 z-30 grid border-t-2 border-slate-200 bg-white pb-[env(safe-area-inset-bottom)] md:hidden"
                 :style="{ gridTemplateColumns: `repeat(${nav.length}, minmax(0, 1fr))` }"
                 :aria-label="$t('Основное меню')">
                <RouterLink
                    v-for="item in nav"
                    :key="item.to"
                    :to="item.to"
                    class="relative flex min-h-16 min-w-0 flex-col items-center justify-center gap-0.5 font-semibold tracking-tight text-slate-600"
                    :style="{ fontSize: 'min(0.875rem, 3.4vw)' /* one line even on a 320 px phone with a wide system font (e.g. DejaVu on Linux) */ }"
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
