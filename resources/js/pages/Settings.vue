<script setup>
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { api, errorText, fieldErrors } from '../api';
import Icon from '../components/Icon.vue';
import Modal from '../components/Modal.vue';
import TwoFactorSetup from '../components/TwoFactorSetup.vue';
import { LOCALES, getLocale, setLocale } from '../i18n';
import { setUser, store, toast } from '../store';
import { t } from '../i18n';

const profile = reactive({ name: store.user.name, email: store.user.email, avatar_color: store.user.avatar_color });
const pwd = reactive({ current_password: '', password: '', password_confirmation: '' });
const pwdErrors = ref({});
const prefs = reactive({ ...store.user.preferences });
const pushState = ref('unknown'); // unsupported | denied | off | on
const settingUp2fa = ref(false);
const disable2fa = ref(false);
const disablePassword = ref('');
const telegramWaiting = ref(false);
let telegramTimer;

async function linkTelegram() {
    try {
        const { data } = await api.post('/telegram/link');
        window.open(data.url, '_blank', 'noopener');
        // Wait until the user taps "Start" in Telegram.
        telegramWaiting.value = true;
        clearInterval(telegramTimer);
        let tries = 0;
        telegramTimer = setInterval(async () => {
            const me = (await api.get('/me')).data.user;
            if (me?.telegram_linked || ++tries > 60) {
                clearInterval(telegramTimer);
                telegramWaiting.value = false;
                if (me?.telegram_linked) {
                    setUser(me);
                    toast('Telegram подключён');
                }
            }
        }, 3000);
    } catch (e) {
        toast(errorText(e), 'error');
    }
}

async function unlinkTelegram() {
    await api.delete('/telegram/link');
    setUser({ ...store.user, telegram_linked: false });
    toast('Telegram отключён');
}

onBeforeUnmount(() => clearInterval(telegramTimer));

const COLORS = ['#1d4ed8', '#15803d', '#b91c1c', '#7e22ce', '#c2410c', '#0f766e', '#be185d', '#334155'];
const SCALES = [
    { value: 1, label: t('Обычный') },
    { value: 1.25, label: t('Крупный') },
    { value: 1.5, label: t('Очень крупный') },
    { value: 1.75, label: t('Максимальный') },
];
const CHANNELS = [
    { key: 'push_logistics', label: t('Перемещения семьи'), hint: t('Срочные — приходят со звуком и вибрацией'), icon: 'bell-ring' },
    { key: 'push_projects', label: t('Задачи и проекты'), hint: t('Тихо, без звука'), icon: 'list-checks' },
    { key: 'push_maintenance', label: t('Напоминания по дому'), hint: t('Тихо, без звука'), icon: 'wrench' },
    { key: 'push_chat', label: t('Сообщения в чатах'), hint: t('Тихо, без звука'), icon: 'message-circle' },
];

onMounted(async () => {
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !store.config.vapid_public_key) {
        pushState.value = 'unsupported';
        return;
    }
    if (Notification.permission === 'denied') {
        pushState.value = 'denied';
        return;
    }
    const reg = await navigator.serviceWorker.ready;
    pushState.value = (await reg.pushManager.getSubscription()) ? 'on' : 'off';
});

async function saveProfile() {
    try {
        setUser((await api.patch('/profile', profile)).data.user);
        toast('Профиль сохранён');
    } catch (e) {
        toast(errorText(e), 'error');
    }
}

async function savePrefs(patch) {
    Object.assign(prefs, patch);
    setUser({ ...store.user, preferences: { ...prefs } }); // apply instantly
    try {
        setUser((await api.patch('/profile/preferences', patch)).data.user);
    } catch (e) {
        toast(errorText(e), 'error');
    }
}

async function changeLanguage(locale) {
    await savePrefs({ locale });
    setLocale(locale); // reloads the page in the new language
}

async function changePassword() {
    pwdErrors.value = {};
    try {
        await api.put('/profile/password', pwd);
        Object.assign(pwd, { current_password: '', password: '', password_confirmation: '' });
        toast('Пароль изменён');
    } catch (e) {
        pwdErrors.value = fieldErrors(e);
    }
}

function urlBase64ToUint8Array(base64) {
    const padding = '='.repeat((4 - (base64.length % 4)) % 4);
    const raw = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
}

async function enablePush() {
    try {
        if ((await Notification.requestPermission()) !== 'granted') {
            pushState.value = 'denied';
            return;
        }
        const reg = await navigator.serviceWorker.ready;
        const sub = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(store.config.vapid_public_key),
        });
        await api.post('/push/subscribe', sub.toJSON());
        pushState.value = 'on';
        toast('Уведомления на этом устройстве включены');
    } catch (e) {
        toast('Не удалось включить уведомления: ' + (e.message || e), 'error');
    }
}

async function disablePush() {
    const reg = await navigator.serviceWorker.ready;
    const sub = await reg.pushManager.getSubscription();
    if (sub) {
        await api.post('/push/unsubscribe', { endpoint: sub.endpoint });
        await sub.unsubscribe();
    }
    pushState.value = 'off';
}

async function turnOff2fa() {
    try {
        await api.delete('/two-factor', { data: { password: disablePassword.value } });
        setUser({ ...store.user, two_factor_enabled: false });
        disable2fa.value = false;
        disablePassword.value = '';
        toast('Двухфакторная защита выключена');
    } catch (e) {
        toast(errorText(e), 'error');
    }
}
</script>

<template>
    <div class="space-y-6">
        <h1 class="page-title">{{ $t('Настройки') }}</h1>

        <!-- Accessibility first: it matters most for older relatives -->
        <section class="card space-y-4" aria-labelledby="a11y">
            <h2 id="a11y" class="flex items-center gap-2 text-xl font-bold"><Icon name="eye" /> {{ $t('Удобство чтения') }}</h2>
            <fieldset>
                <legend class="field-label">Мова / Язык</legend>
                <div class="grid grid-cols-2 gap-2">
                    <button v-for="(label, code) in LOCALES" :key="code" class="btn"
                            :class="getLocale() === code ? 'bg-brand-700 text-white' : 'bg-white ring-2 ring-slate-300'"
                            :aria-pressed="getLocale() === code" @click="changeLanguage(code)">{{ label }}</button>
                </div>
            </fieldset>
            <fieldset>
                <legend class="field-label">{{ $t('Размер текста') }}</legend>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <button v-for="s in SCALES" :key="s.value" class="btn flex-col gap-0 py-2 leading-tight"
                            :class="prefs.font_scale === s.value ? 'bg-brand-700 text-white' : 'bg-white ring-2 ring-slate-300'"
                            :aria-pressed="prefs.font_scale === s.value"
                            @click="savePrefs({ font_scale: s.value })">
                        <!-- Preview letters in 16px-based sizes, so the buttons fit whatever size is active now. -->
                        <span :style="{ fontSize: `${16 * s.value}px` }" aria-hidden="true">{{ $t('Аа') }}</span>
                        <span class="text-sm">{{ s.label }}</span>
                    </button>
                </div>
            </fieldset>
            <label class="flex min-h-12 cursor-pointer items-center justify-between gap-3 rounded-xl bg-slate-50 p-3">
                <span><b class="block">{{ $t('Высокая контрастность') }}</b><span class="text-slate-600">{{ $t('Чёрный текст на белом, толстые рамки') }}</span></span>
                <input type="checkbox" class="h-7 w-7" :checked="prefs.high_contrast" @change="savePrefs({ high_contrast: $event.target.checked })">
            </label>
            <label v-if="store.user.can_write" class="flex min-h-12 cursor-pointer items-center justify-between gap-3 rounded-xl bg-slate-50 p-3">
                <span><b class="block">{{ $t('Простой режим') }}</b><span class="text-slate-600">{{ $t('Крупные плитки на главной, меньше пунктов меню') }}</span></span>
                <input type="checkbox" class="h-7 w-7" :checked="prefs.simple_mode" @change="savePrefs({ simple_mode: $event.target.checked })">
            </label>
        </section>

        <section id="notifications" class="card space-y-4" aria-labelledby="notif">
            <h2 id="notif" class="flex items-center gap-2 text-xl font-bold"><Icon name="bell" /> {{ $t('Уведомления') }}</h2>
            <div class="rounded-xl bg-slate-50 p-3">
                <template v-if="pushState === 'on'">
                    <p class="mb-2 font-semibold text-emerald-800">{{ $t('Пуш-уведомления на этом устройстве включены.') }}</p>
                    <button class="btn-secondary" @click="disablePush"><Icon name="bell-off" /> {{ $t('Выключить на этом устройстве') }}</button>
                </template>
                <template v-else-if="pushState === 'off'">
                    <p class="mb-2">{{ $t('Получайте уведомления, даже когда приложение закрыто.') }}</p>
                    <button class="btn-primary" @click="enablePush"><Icon name="bell-ring" /> {{ $t('Включить на этом устройстве') }}</button>
                </template>
                <p v-else-if="pushState === 'denied'">{{ $t('Уведомления запрещены в настройках браузера. Разрешите их для этого сайта и обновите страницу.') }}</p>
                <p v-else-if="pushState === 'unsupported'">
                    {{ $t('Пуш-уведомления недоступны: браузер их не поддерживает или сервер не настроен. На iPhone сначала добавьте сайт на экран «Домой». Уведомления всё равно видны в разделе «Уведомления».') }}
                </p>
            </div>
            <div class="space-y-2">
                <label v-for="c in CHANNELS" :key="c.key" class="flex min-h-12 cursor-pointer items-center gap-3 rounded-xl bg-slate-50 p-3">
                    <Icon :name="c.icon" />
                    <span class="flex-1"><b class="block">{{ c.label }}</b><span class="text-slate-600">{{ c.hint }}</span></span>
                    <input type="checkbox" class="h-7 w-7" :checked="prefs[c.key]" @change="savePrefs({ [c.key]: $event.target.checked })">
                </label>
            </div>

            <div class="space-y-3 rounded-xl bg-sky-50 p-3">
                <h3 class="flex items-center gap-2 text-lg font-bold"><Icon name="send" /> Telegram</h3>
                <template v-if="!store.config.telegram_enabled">
                    <p class="text-slate-700">{{ $t('Бот ещё не настроен на сервере. Попросите администратора добавить его (инструкция в docs/DEPLOY.md).') }}</p>
                </template>
                <template v-else-if="store.user.telegram_linked">
                    <p class="font-semibold text-emerald-800">{{ $t('Подключено. Срочные статусы приходят в Telegram со звуком.') }}</p>
                    <label class="flex min-h-12 cursor-pointer items-center gap-3">
                        <span class="flex-1"><b class="block">{{ $t('Присылать и обычные уведомления') }}</b><span class="text-slate-600">{{ $t('Задачи, чаты, дом — без звука') }}</span></span>
                        <input type="checkbox" class="h-7 w-7" :checked="prefs.telegram_all" @change="savePrefs({ telegram_all: $event.target.checked })">
                    </label>
                    <button class="btn-secondary" @click="unlinkTelegram">{{ $t('Отключить Telegram') }}</button>
                </template>
                <template v-else>
                    <p>{{ $t('Надёжный способ получать срочное: «Граница пройдена», «В укрытии», «Все ли в порядке?» — даже если пуши в браузере не работают.') }}</p>
                    <button class="btn-primary" @click="linkTelegram"><Icon name="send" /> {{ $t('Подключить Telegram') }}</button>
                    <p v-if="telegramWaiting" class="text-slate-700" role="status">{{ $t('Откройте бота в Telegram и нажмите «Запустить» / «Start». Ждём подтверждения…') }}</p>
                </template>
            </div>
        </section>

        <section class="card space-y-4" aria-labelledby="sec">
            <h2 id="sec" class="flex items-center gap-2 text-xl font-bold"><Icon name="shield-check" /> {{ $t('Безопасность') }}</h2>
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-slate-50 p-3">
                <span>
                    <b class="block">{{ $t('Двухфакторная аутентификация') }}</b>
                    <span :class="store.user.two_factor_enabled ? 'text-emerald-800' : 'text-slate-600'">
                        {{ store.user.two_factor_enabled ? $t('Включена') : $t('Выключена — нужна для доступа к билетам и документам') }}
                    </span>
                </span>
                <button v-if="!store.user.two_factor_enabled" class="btn-primary" @click="settingUp2fa = true">{{ $t('Включить') }}</button>
                <button v-else class="btn-secondary" @click="disable2fa = true">{{ $t('Выключить') }}</button>
            </div>

            <form class="grid gap-3 sm:grid-cols-3" @submit.prevent="changePassword">
                <div>
                    <label class="field-label" for="p-cur">{{ $t('Текущий пароль') }}</label>
                    <input id="p-cur" v-model="pwd.current_password" type="password" class="field" autocomplete="current-password" required>
                    <p v-if="pwdErrors.current_password" class="field-error">{{ pwdErrors.current_password }}</p>
                </div>
                <div>
                    <label class="field-label" for="p-new">{{ $t('Новый пароль') }}</label>
                    <input id="p-new" v-model="pwd.password" type="password" class="field" autocomplete="new-password" required>
                    <p v-if="pwdErrors.password" class="field-error">{{ pwdErrors.password }}</p>
                </div>
                <div>
                    <label class="field-label" for="p-new2">{{ $t('Ещё раз') }}</label>
                    <input id="p-new2" v-model="pwd.password_confirmation" type="password" class="field" autocomplete="new-password" required>
                </div>
                <button class="btn-secondary sm:col-span-3"><Icon name="key-round" /> {{ $t('Сменить пароль') }}</button>
            </form>
        </section>

        <section class="card space-y-4" aria-labelledby="prof">
            <h2 id="prof" class="flex items-center gap-2 text-xl font-bold"><Icon name="users" /> {{ $t('Профиль') }}</h2>
            <form class="space-y-4" @submit.prevent="saveProfile">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="field-label" for="pr-name">{{ $t('Имя') }}</label>
                        <input id="pr-name" v-model="profile.name" class="field" required>
                    </div>
                    <div>
                        <label class="field-label" for="pr-email">Email</label>
                        <input id="pr-email" v-model="profile.email" type="email" class="field" required>
                    </div>
                </div>
                <fieldset>
                    <legend class="field-label">{{ $t('Цвет аватара') }}</legend>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="c in COLORS" :key="c" type="button" class="h-12 w-12 rounded-full ring-offset-2"
                                :class="profile.avatar_color === c ? 'ring-4 ring-slate-900' : ''" :style="{ background: c }"
                                :aria-label="$t('Цвет :p0', { p0: c })" :aria-pressed="profile.avatar_color === c" @click="profile.avatar_color = c" />
                    </div>
                </fieldset>
                <button class="btn-primary">{{ $t('Сохранить профиль') }}</button>
            </form>
        </section>

        <Modal v-if="settingUp2fa" :title="$t('Двухфакторная аутентификация')" @close="settingUp2fa = false">
            <TwoFactorSetup @enabled="settingUp2fa = false" />
        </Modal>

        <Modal v-if="disable2fa" :title="$t('Выключить 2FA')" @close="disable2fa = false">
            <form class="space-y-4" @submit.prevent="turnOff2fa">
                <p>{{ $t('Без второго фактора раздел с документами станет недоступен.') }}</p>
                <label class="field-label" for="d2-pass">{{ $t('Пароль') }}</label>
                <input id="d2-pass" v-model="disablePassword" type="password" class="field" required>
                <button class="btn-danger w-full">{{ $t('Выключить') }}</button>
            </form>
        </Modal>
    </div>
</template>
