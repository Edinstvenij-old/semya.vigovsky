<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api, errorText, fieldErrors } from '../api';
import AuthCard from '../components/AuthCard.vue';
import { setUser, startPolling, store } from '../store';

const route = useRoute();
const router = useRouter();
const invite = ref(null);
const loadError = ref('');
const form = reactive({ name: '', email: '', password: '', password_confirmation: '', relation: '' });
const errors = ref({});
const error = ref('');
const busy = ref(false);

onMounted(async () => {
    try {
        invite.value = (await api.get(`/invitations/${route.params.token}`)).data;
    } catch (e) {
        loadError.value = errorText(e);
    }
});

async function accept() {
    busy.value = true;
    errors.value = {};
    error.value = '';
    try {
        const { data } = await api.post(`/invitations/${route.params.token}/accept`, form);
        setUser(data.user);
        startPolling();
        router.push('/');
    } catch (e) {
        errors.value = fieldErrors(e);
        if (!Object.keys(errors.value).length) error.value = errorText(e);
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <AuthCard :title="$t('Приглашение')" :subtitle="invite ? $t('в семью «:p0»', { p0: invite.family }) : ''">
        <p v-if="loadError" class="rounded-xl bg-red-50 p-4 font-semibold text-red-800" role="alert">{{ loadError }}</p>

        <template v-else-if="invite">
            <p class="mb-4 rounded-xl bg-brand-50 p-4">
                <b>{{ invite.invited_by }}</b> {{ $t('приглашает вас в семью') }} <b>«{{ invite.family }}»</b>{{ $t('. Роль:') }} <b>{{ invite.role_label }}</b>.
            </p>

            <form class="space-y-4" @submit.prevent="accept">
                <template v-if="store.user">
                    <p>{{ $t('Вы вошли как') }} <b>{{ store.user.name }}</b>{{ $t('. Семья будет добавлена к вашему аккаунту.') }}</p>
                </template>
                <template v-else>
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
                        <label class="field-label" for="password">{{ $t('Придумайте пароль (минимум 10 символов)') }}</label>
                        <input id="password" v-model="form.password" class="field" type="password" autocomplete="new-password" required>
                        <p v-if="errors.password" class="field-error">{{ errors.password }}</p>
                    </div>
                    <div>
                        <label class="field-label" for="password2">{{ $t('Пароль ещё раз') }}</label>
                        <input id="password2" v-model="form.password_confirmation" class="field" type="password" autocomplete="new-password" required>
                    </div>
                </template>
                <div>
                    <label class="field-label" for="relation">{{ $t('Кем вы приходитесь семье? (необязательно)') }}</label>
                    <input id="relation" v-model="form.relation" class="field" :placeholder="$t('бабушка, сын, тётя…')">
                </div>
                <p v-if="error" class="field-error" role="alert">{{ error }}</p>
                <button class="btn-primary w-full text-lg" :disabled="busy">{{ $t('Присоединиться') }}</button>
                <RouterLink v-if="!store.user" :to="{ path: '/login', query: { redirect: route.fullPath } }" class="btn-ghost w-full">
                    {{ $t('У меня уже есть аккаунт — войти') }}
                </RouterLink>
            </form>
        </template>
        <p v-else class="text-slate-600">{{ $t('Загрузка…') }}</p>
    </AuthCard>
</template>
