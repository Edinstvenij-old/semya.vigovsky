<script setup>
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api, errorText } from '../api';
import EmptyState from '../components/EmptyState.vue';
import Icon from '../components/Icon.vue';
import Modal from '../components/Modal.vue';
import { ago } from '../format';
import { onLive } from '../live';
import { store, toast } from '../store';

const router = useRouter();
const threads = ref([]);
const creating = ref(false);
const form = reactive({ title: '', icon: 'message-circle', message: '' });

const ICONS = [
    ['message-circle', 'Разговор'], ['shopping-cart', 'Покупка'], ['plane', 'Поездка'],
    ['home', 'Дом'], ['wrench', 'Ремонт'], ['calendar-days', 'Встреча'],
];

async function load() {
    threads.value = (await api.get('/threads')).data;
}

let off;
onMounted(() => {
    load();
    off = onLive('message', load);
});
onBeforeUnmount(() => off?.());

async function create() {
    try {
        const { data } = await api.post('/threads', form);
        creating.value = false;
        router.push(`/threads/${data.id}`);
    } catch (e) {
        toast(errorText(e), 'error');
    }
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="page-title">Обсуждения</h1>
            <button v-if="store.user.can_write" class="btn-primary" @click="creating = true"><Icon name="plus" /> Новая тема</button>
        </div>
        <p class="text-slate-600">Каждая тема — отдельный разговор: покупка, визит, поездка. Так ничего не теряется в общем потоке.</p>

        <ul class="space-y-2">
            <li v-for="t in threads" :key="t.id">
                <RouterLink :to="`/threads/${t.id}`" class="card flex items-center gap-3 hover:ring-2 hover:ring-brand-600">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl"
                          :class="t.unread_count ? 'bg-brand-700 text-white' : 'bg-slate-100 text-slate-700'">
                        <Icon :name="t.icon" size="1.5em" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <Icon v-if="t.is_pinned" name="pin" size="1em" class="text-slate-500" />
                            <h2 class="truncate text-lg font-bold">{{ t.title }}</h2>
                            <Icon v-if="Number(t.muted)" name="bell-off" size="1em" class="text-slate-500" />
                        </div>
                        <p class="truncate text-slate-600" :class="t.unread_count ? 'font-semibold text-slate-900' : ''">
                            <template v-if="t.last_message">{{ t.last_message.user?.name }}: {{ t.last_message.body }}</template>
                            <template v-else>Пока без сообщений</template>
                        </p>
                    </div>
                    <div class="flex max-w-[35%] flex-col items-end gap-1 text-right">
                        <span class="text-sm text-slate-600">{{ ago(t.last_message_at || t.created_at) }}</span>
                        <span v-if="t.unread_count" class="rounded-full bg-red-600 px-2 text-sm font-bold text-white">{{ t.unread_count }}</span>
                    </div>
                </RouterLink>
            </li>
        </ul>
        <EmptyState v-if="!threads.length" icon="message-circle" text="Тем пока нет" />

        <Modal v-if="creating" title="Новая тема" @close="creating = false">
            <form class="space-y-4" @submit.prevent="create">
                <div>
                    <label class="field-label" for="th-title">О чём поговорим?</label>
                    <input id="th-title" v-model="form.title" class="field" required placeholder="Подарок дедушке на юбилей">
                </div>
                <fieldset>
                    <legend class="field-label">Значок</legend>
                    <div class="grid grid-cols-3 gap-2">
                        <label v-for="[icon, label] in ICONS" :key="icon"
                               class="flex min-h-12 cursor-pointer items-center gap-2 rounded-xl px-3 ring-2"
                               :class="form.icon === icon ? 'bg-brand-50 ring-brand-600' : 'ring-slate-200'">
                            <input v-model="form.icon" type="radio" :value="icon" class="sr-only">
                            <Icon :name="icon" /> <span class="text-sm">{{ label }}</span>
                        </label>
                    </div>
                </fieldset>
                <div>
                    <label class="field-label" for="th-msg">Первое сообщение</label>
                    <textarea id="th-msg" v-model="form.message" class="field" rows="3" />
                </div>
                <button class="btn-primary w-full text-lg">Создать</button>
            </form>
        </Modal>
    </div>
</template>
