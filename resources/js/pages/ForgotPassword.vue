<script setup>
import { ref } from 'vue';
import { api, errorText } from '../api';
import AuthCard from '../components/AuthCard.vue';

const email = ref('');
const sent = ref('');
const error = ref('');
const busy = ref(false);

async function submit() {
    busy.value = true;
    error.value = '';
    try {
        sent.value = (await api.post('/forgot-password', { email: email.value })).data.message;
    } catch (e) {
        error.value = errorText(e);
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <AuthCard :title="$t('Забыли пароль?')" :subtitle="$t('Пришлём ссылку для нового пароля')">
        <div v-if="sent" class="space-y-4">
            <p class="rounded-xl bg-emerald-50 p-4 text-emerald-900" role="status">{{ sent }}</p>
            <RouterLink to="/login" class="btn-secondary w-full">{{ $t('Вернуться ко входу') }}</RouterLink>
        </div>
        <form v-else class="space-y-4" @submit.prevent="submit">
            <div>
                <label class="field-label" for="email">{{ $t('Ваш email') }}</label>
                <input id="email" v-model="email" class="field" type="email" autocomplete="username" required>
            </div>
            <p v-if="error" class="field-error" role="alert">{{ error }}</p>
            <button class="btn-primary w-full text-lg" :disabled="busy">{{ $t('Отправить ссылку') }}</button>
            <p class="text-slate-600">{{ $t('Нет доступа к почте? Попросите администратора семьи — он может создать ссылку для сброса в разделе «Семья».') }}</p>
            <RouterLink to="/login" class="btn-ghost w-full">{{ $t('Я вспомнил(а) пароль') }}</RouterLink>
        </form>
    </AuthCard>
</template>
