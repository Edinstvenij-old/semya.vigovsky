<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api, errorText } from '../api';
import AuthCard from '../components/AuthCard.vue';
import { setUser, startPolling, store } from '../store';

const router = useRouter();
const route = useRoute();
const form = reactive({ email: '', password: '' });
const error = ref('');
const busy = ref(false);

async function submit() {
    busy.value = true;
    error.value = '';
    try {
        const { data } = await api.post('/login', form);
        setUser(data.user);
        startPolling();
        router.push(route.query.redirect || '/');
    } catch (e) {
        error.value = errorText(e);
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <AuthCard :title="store.config.app_name" subtitle="Закрытое пространство семьи">
        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label class="field-label" for="email">Email</label>
                <input id="email" v-model="form.email" class="field" type="email" autocomplete="username" required>
            </div>
            <div>
                <label class="field-label" for="password">Пароль</label>
                <input id="password" v-model="form.password" class="field" type="password" autocomplete="current-password" required>
            </div>
            <p v-if="error" class="field-error" role="alert">{{ error }}</p>
            <button class="btn-primary w-full text-lg" :disabled="busy">Войти</button>
            <RouterLink to="/forgot-password" class="btn-ghost w-full">Забыли пароль?</RouterLink>
        </form>
        <p class="mt-6 text-center text-slate-600">
            Нет аккаунта? Попросите ссылку-приглашение у администратора семьи.
        </p>
        <p v-if="store.config.registration_open" class="mt-3 text-center">
            <RouterLink to="/register" class="font-semibold text-brand-700 underline">Создать новую семью</RouterLink>
        </p>
    </AuthCard>
</template>
