<script setup>
import { ref } from 'vue';
import { api, errorText } from '../api';
import { setUser, store, toast } from '../store';
import Icon from './Icon.vue';

const emit = defineEmits(['enabled']);
const step = ref('password');
const password = ref('');
const setup = ref(null);
const code = ref('');
const codes = ref([]);
const error = ref('');
const busy = ref(false);

async function start() {
    busy.value = true;
    error.value = '';
    try {
        setup.value = (await api.post('/two-factor/setup', { password: password.value })).data;
        step.value = 'scan';
    } catch (e) {
        error.value = errorText(e);
    } finally {
        busy.value = false;
    }
}

async function confirm() {
    busy.value = true;
    error.value = '';
    try {
        codes.value = (await api.post('/two-factor/confirm', { code: code.value })).data.recovery_codes;
        step.value = 'codes';
        setUser({ ...store.user, two_factor_enabled: true });
    } catch (e) {
        error.value = errorText(e);
    } finally {
        busy.value = false;
    }
}

function copyCodes() {
    navigator.clipboard?.writeText(codes.value.join('\n'));
    toast('Коды скопированы');
}
</script>

<template>
    <div class="space-y-4">
        <form v-if="step === 'password'" class="space-y-4" @submit.prevent="start">
            <p>{{ $t('Двухфакторная защита нужна для доступа к билетам и документам. Понадобится приложение-аутентификатор на телефоне (Google Authenticator, Microsoft Authenticator, Aegis, 1Password и т. п.).') }}</p>
            <div>
                <label class="field-label" for="tf-pass">{{ $t('Ваш пароль') }}</label>
                <input id="tf-pass" v-model="password" type="password" class="field" autocomplete="current-password" required>
            </div>
            <p v-if="error" class="field-error" role="alert">{{ error }}</p>
            <button class="btn-primary w-full" :disabled="busy"><Icon name="shield-check" /> {{ $t('Начать настройку') }}</button>
        </form>

        <form v-else-if="step === 'scan'" class="space-y-4" @submit.prevent="confirm">
            <p><b>1.</b> {{ $t('Отсканируйте QR-код в приложении-аутентификаторе:') }}</p>
            <div class="mx-auto w-fit rounded-xl bg-white p-2 ring-1 ring-slate-200" v-html="setup.qr_svg" />
            <details class="text-sm">
                <summary class="cursor-pointer font-semibold">{{ $t('Не получается отсканировать? Введите ключ вручную') }}</summary>
                <code class="mt-2 block break-all rounded bg-slate-100 p-2 text-base">{{ setup.secret }}</code>
            </details>
            <div>
                <label class="field-label" for="tf-code"><b>2.</b> {{ $t('Введите 6 цифр из приложения') }}</label>
                <input id="tf-code" v-model="code" class="field text-center text-2xl tracking-widest" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required>
            </div>
            <p v-if="error" class="field-error" role="alert">{{ error }}</p>
            <button class="btn-primary w-full" :disabled="busy">{{ $t('Подтвердить') }}</button>
        </form>

        <div v-else class="space-y-4">
            <p class="rounded-xl bg-emerald-50 p-3 font-semibold text-emerald-900">{{ $t('Двухфакторная защита включена.') }}</p>
            <p>{{ $t('Сохраните резервные коды в надёжном месте. Каждый код можно использовать один раз, если телефон потерян.') }}</p>
            <ul class="grid grid-cols-2 gap-2 rounded-xl bg-slate-100 p-3 font-mono text-lg">
                <li v-for="c in codes" :key="c">{{ c }}</li>
            </ul>
            <div class="flex gap-2">
                <button class="btn-secondary flex-1" @click="copyCodes"><Icon name="copy" /> {{ $t('Скопировать') }}</button>
                <button class="btn-primary flex-1" @click="emit('enabled')">{{ $t('Готово') }}</button>
            </div>
        </div>
    </div>
</template>
