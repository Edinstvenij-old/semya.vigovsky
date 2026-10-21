<script setup>
import { reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api, errorText, fieldErrors } from '../api';
import AuthCard from '../components/AuthCard.vue';

const route = useRoute();
const form = reactive({
    token: route.params.token,
    email: route.query.email || '',
    password: '',
    password_confirmation: '',
});
const errors = ref({});
const error = ref('');
const done = ref('');
const busy = ref(false);

async function submit() {
    busy.value = true;
    errors.value = {};
    error.value = '';
    try {
        done.value = (await api.post('/reset-password', form)).data.message;
    } catch (e) {
        errors.value = fieldErrors(e);
        if (!Object.keys(errors.value).length) error.value = errorText(e);
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <AuthCard title="Новый пароль" :subtitle="form.email">
        <div v-if="done" class="space-y-4">
            <p class="rounded-xl bg-emerald-50 p-4 text-emerald-900" role="status">{{ done }}</p>
            <RouterLink to="/login" class="btn-primary w-full text-lg">Войти</RouterLink>
        </div>
        <form v-else class="space-y-4" @submit.prevent="submit">
            <input type="hidden" name="username" :value="form.email" autocomplete="username">
            <div>
                <label class="field-label" for="password">Новый пароль (минимум 10 символов)</label>
                <input id="password" v-model="form.password" class="field" type="password" autocomplete="new-password" required>
                <p v-if="errors.password" class="field-error">{{ errors.password }}</p>
            </div>
            <div>
                <label class="field-label" for="password2">Ещё раз</label>
                <input id="password2" v-model="form.password_confirmation" class="field" type="password" autocomplete="new-password" required>
            </div>
            <p v-if="errors.email || error" class="field-error" role="alert">{{ errors.email || error }}</p>
            <button class="btn-primary w-full text-lg" :disabled="busy">Сохранить пароль</button>
            <RouterLink v-if="errors.email" to="/forgot-password" class="btn-ghost w-full">Запросить новую ссылку</RouterLink>
        </form>
    </AuthCard>
</template>
