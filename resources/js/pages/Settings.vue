<script setup>
import { onMounted, reactive, ref } from 'vue';
import { api, errorText, fieldErrors } from '../api';
import Icon from '../components/Icon.vue';
import Modal from '../components/Modal.vue';
import TwoFactorSetup from '../components/TwoFactorSetup.vue';
import { setUser, store, toast } from '../store';

const profile = reactive({ name: store.user.name, email: store.user.email, avatar_color: store.user.avatar_color });
const pwd = reactive({ current_password: '', password: '', password_confirmation: '' });
const pwdErrors = ref({});
const prefs = reactive({ ...store.user.preferences });
const pushState = ref('unknown'); // unsupported | denied | off | on
const settingUp2fa = ref(false);
const disable2fa = ref(false);
const disablePassword = ref('');

const COLORS = ['#1d4ed8', '#15803d', '#b91c1c', '#7e22ce', '#c2410c', '#0f766e', '#be185d', '#334155'];
const SCALES = [
    { value: 1, label: 'Обычный' },
    { value: 1.25, label: 'Крупный' },
    { value: 1.5, label: 'Очень крупный' },
    { value: 1.75, label: 'Максимальный' },
];
const CHANNELS = [
    { key: 'push_logistics', label: 'Перемещения семьи', hint: 'Срочные — приходят со звуком и вибрацией', icon: 'bell-ring' },
    { key: 'push_projects', label: 'Задачи и проекты', hint: 'Тихо, без звука', icon: 'list-checks' },
    { key: 'push_maintenance', label: 'Напоминания по дому', hint: 'Тихо, без звука', icon: 'wrench' },
    { key: 'push_chat', label: 'Сообщения в чатах', hint: 'Тихо, без звука', icon: 'message-circle' },
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
        <h1 class="page-title">Настройки</h1>

        <!-- Accessibility first: it matters most for older relatives -->
        <section class="card space-y-4" aria-labelledby="a11y">
            <h2 id="a11y" class="flex items-center gap-2 text-xl font-bold"><Icon name="eye" /> Удобство чтения</h2>
            <fieldset>
                <legend class="field-label">Размер текста</legend>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <button v-for="s in SCALES" :key="s.value" class="btn flex-col gap-0 py-2 leading-tight"
                            :class="prefs.font_scale === s.value ? 'bg-brand-700 text-white' : 'bg-white ring-2 ring-slate-300'"
                            :aria-pressed="prefs.font_scale === s.value"
                            @click="savePrefs({ font_scale: s.value })">
                        <!-- Preview letters in 16px-based sizes, so the buttons fit whatever size is active now. -->
                        <span :style="{ fontSize: `${16 * s.value}px` }" aria-hidden="true">Аа</span>
                        <span class="text-sm">{{ s.label }}</span>
                    </button>
                </div>
            </fieldset>
            <label class="flex min-h-12 cursor-pointer items-center justify-between gap-3 rounded-xl bg-slate-50 p-3">
                <span><b class="block">Высокая контрастность</b><span class="text-slate-600">Чёрный текст на белом, толстые рамки</span></span>
                <input type="checkbox" class="h-7 w-7" :checked="prefs.high_contrast" @change="savePrefs({ high_contrast: $event.target.checked })">
            </label>
            <label v-if="store.user.can_write" class="flex min-h-12 cursor-pointer items-center justify-between gap-3 rounded-xl bg-slate-50 p-3">
                <span><b class="block">Простой режим</b><span class="text-slate-600">Крупные плитки на главной, меньше пунктов меню</span></span>
                <input type="checkbox" class="h-7 w-7" :checked="prefs.simple_mode" @change="savePrefs({ simple_mode: $event.target.checked })">
            </label>
        </section>

        <section id="notifications" class="card space-y-4" aria-labelledby="notif">
            <h2 id="notif" class="flex items-center gap-2 text-xl font-bold"><Icon name="bell" /> Уведомления</h2>
            <div class="rounded-xl bg-slate-50 p-3">
                <template v-if="pushState === 'on'">
                    <p class="mb-2 font-semibold text-emerald-800">Пуш-уведомления на этом устройстве включены.</p>
                    <button class="btn-secondary" @click="disablePush"><Icon name="bell-off" /> Выключить на этом устройстве</button>
                </template>
                <template v-else-if="pushState === 'off'">
                    <p class="mb-2">Получайте уведомления, даже когда приложение закрыто.</p>
                    <button class="btn-primary" @click="enablePush"><Icon name="bell-ring" /> Включить на этом устройстве</button>
                </template>
                <p v-else-if="pushState === 'denied'">Уведомления запрещены в настройках браузера. Разрешите их для этого сайта и обновите страницу.</p>
                <p v-else-if="pushState === 'unsupported'">
                    Пуш-уведомления недоступны: браузер их не поддерживает или сервер не настроен. На iPhone сначала добавьте сайт на экран «Домой».
                    Уведомления всё равно видны в разделе «Уведомления».
                </p>
            </div>
            <div class="space-y-2">
                <label v-for="c in CHANNELS" :key="c.key" class="flex min-h-12 cursor-pointer items-center gap-3 rounded-xl bg-slate-50 p-3">
                    <Icon :name="c.icon" />
                    <span class="flex-1"><b class="block">{{ c.label }}</b><span class="text-slate-600">{{ c.hint }}</span></span>
                    <input type="checkbox" class="h-7 w-7" :checked="prefs[c.key]" @change="savePrefs({ [c.key]: $event.target.checked })">
                </label>
            </div>
        </section>

        <section class="card space-y-4" aria-labelledby="sec">
            <h2 id="sec" class="flex items-center gap-2 text-xl font-bold"><Icon name="shield-check" /> Безопасность</h2>
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-slate-50 p-3">
                <span>
                    <b class="block">Двухфакторная аутентификация</b>
                    <span :class="store.user.two_factor_enabled ? 'text-emerald-800' : 'text-slate-600'">
                        {{ store.user.two_factor_enabled ? 'Включена' : 'Выключена — нужна для доступа к билетам и документам' }}
                    </span>
                </span>
                <button v-if="!store.user.two_factor_enabled" class="btn-primary" @click="settingUp2fa = true">Включить</button>
                <button v-else class="btn-secondary" @click="disable2fa = true">Выключить</button>
            </div>

            <form class="grid gap-3 sm:grid-cols-3" @submit.prevent="changePassword">
                <div>
                    <label class="field-label" for="p-cur">Текущий пароль</label>
                    <input id="p-cur" v-model="pwd.current_password" type="password" class="field" autocomplete="current-password" required>
                    <p v-if="pwdErrors.current_password" class="field-error">{{ pwdErrors.current_password }}</p>
                </div>
                <div>
                    <label class="field-label" for="p-new">Новый пароль</label>
                    <input id="p-new" v-model="pwd.password" type="password" class="field" autocomplete="new-password" required>
                    <p v-if="pwdErrors.password" class="field-error">{{ pwdErrors.password }}</p>
                </div>
                <div>
                    <label class="field-label" for="p-new2">Ещё раз</label>
                    <input id="p-new2" v-model="pwd.password_confirmation" type="password" class="field" autocomplete="new-password" required>
                </div>
                <button class="btn-secondary sm:col-span-3"><Icon name="key-round" /> Сменить пароль</button>
            </form>
        </section>

        <section class="card space-y-4" aria-labelledby="prof">
            <h2 id="prof" class="flex items-center gap-2 text-xl font-bold"><Icon name="users" /> Профиль</h2>
            <form class="space-y-4" @submit.prevent="saveProfile">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="field-label" for="pr-name">Имя</label>
                        <input id="pr-name" v-model="profile.name" class="field" required>
                    </div>
                    <div>
                        <label class="field-label" for="pr-email">Email</label>
                        <input id="pr-email" v-model="profile.email" type="email" class="field" required>
                    </div>
                </div>
                <fieldset>
                    <legend class="field-label">Цвет аватара</legend>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="c in COLORS" :key="c" type="button" class="h-12 w-12 rounded-full ring-offset-2"
                                :class="profile.avatar_color === c ? 'ring-4 ring-slate-900' : ''" :style="{ background: c }"
                                :aria-label="`Цвет ${c}`" :aria-pressed="profile.avatar_color === c" @click="profile.avatar_color = c" />
                    </div>
                </fieldset>
                <button class="btn-primary">Сохранить профиль</button>
            </form>
        </section>

        <Modal v-if="settingUp2fa" title="Двухфакторная аутентификация" @close="settingUp2fa = false">
            <TwoFactorSetup @enabled="settingUp2fa = false" />
        </Modal>

        <Modal v-if="disable2fa" title="Выключить 2FA" @close="disable2fa = false">
            <form class="space-y-4" @submit.prevent="turnOff2fa">
                <p>Без второго фактора раздел с документами станет недоступен.</p>
                <label class="field-label" for="d2-pass">Пароль</label>
                <input id="d2-pass" v-model="disablePassword" type="password" class="field" required>
                <button class="btn-danger w-full">Выключить</button>
            </form>
        </Modal>
    </div>
</template>
