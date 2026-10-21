<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api, fieldErrors, errorText } from '../api';
import AuthCard from '../components/AuthCard.vue';
import { setUser, startPolling } from '../store';

const router = useRouter();
const form = reactive({ family_name: '', name: '', email: '', password: '', password_confirmation: '' });
const errors = ref({});
const error = ref('');
const busy = ref(false);

async function submit() {
    busy.value = true;
    errors.value = {};
    error.value = '';
    try {
        const { data } = await api.post('/register', form);
        setUser(data.user);
        startPolling();
        router.push('/family');
    } catch (e) {
        errors.value = fieldErrors(e);
        if (!Object.keys(errors.value).length) error.value = errorText(e);
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <AuthCard :title="$t('Новая семья')" :subtitle="$t('Вы станете администратором')">
        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label class="field-label" for="family">{{ $t('Название семьи') }}</label>
                <input id="family" v-model="form.family_name" class="field" :placeholder="$t('Например: Ивановы')" required>
                <p v-if="errors.family_name" class="field-error">{{ errors.family_name }}</p>
            </div>
            <div>
                <label class="field-label" for="name">{{ $t('Ваше имя') }}</label>
                <input id="name" v-model="form.name" class="field" autocomplete="name" required>
                <p v-if="errors.name" class="field-error">{{ errors.name }}</p>
            </div>
            <div>
                <label class="field-label" for="email">Email</label>
                <input id="email" v-model="form.email" class="field" type="email" autocomplete="username" required>
                <p v-if="errors.email" class="field-error">{{ errors.email }}</p>
            </div>
            <div>
                <label class="field-label" for="password">{{ $t('Пароль (минимум 10 символов)') }}</label>
                <input id="password" v-model="form.password" class="field" type="password" autocomplete="new-password" required>
                <p v-if="errors.password" class="field-error">{{ errors.password }}</p>
            </div>
            <div>
                <label class="field-label" for="password2">{{ $t('Пароль ещё раз') }}</label>
                <input id="password2" v-model="form.password_confirmation" class="field" type="password" autocomplete="new-password" required>
            </div>
            <p v-if="error" class="field-error" role="alert">{{ error }}</p>
            <button class="btn-primary w-full text-lg" :disabled="busy">{{ $t('Создать семью') }}</button>
            <RouterLink to="/login" class="btn-ghost w-full">{{ $t('У меня уже есть аккаунт') }}</RouterLink>
        </form>
    </AuthCard>
</template>
