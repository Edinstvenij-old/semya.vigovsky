<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api, errorText } from '../api';
import Avatar from '../components/Avatar.vue';
import Icon from '../components/Icon.vue';
import { ago, time } from '../format';
import { refreshCounts, store, toast } from '../store';

const route = useRoute();
const router = useRouter();
const thread = ref(null);
const messages = ref([]);
const body = ref('');
const sending = ref(false);
const muted = ref(false);
const list = ref(null);
let timer;

const lastId = computed(() => messages.value.at(-1)?.id || 0);

async function scrollDown() {
    await nextTick();
    list.value?.scrollIntoView({ block: 'end' });
}

async function markRead() {
    if (lastId.value) {
        await api.post(`/threads/${route.params.id}/read`, { message_id: lastId.value });
        refreshCounts();
    }
}

async function load() {
    const { data } = await api.get(`/threads/${route.params.id}/messages`);
    thread.value = data.thread;
    messages.value = data.messages;
    muted.value = data.muted;
    scrollDown();
    markRead();
}

// Simple polling keeps the MVP free of a websocket server.
async function poll() {
    const { data } = await api.get(`/threads/${route.params.id}/messages`, { params: { after: lastId.value } });
    if (data.messages.length) {
        messages.value.push(...data.messages.filter((m) => !messages.value.some((x) => x.id === m.id)));
        scrollDown();
        markRead();
    }
}

onMounted(() => {
    load().catch(() => router.replace('/threads'));
    timer = setInterval(() => poll().catch(() => {}), 5000);
});
onBeforeUnmount(() => clearInterval(timer));

async function send() {
    if (!body.value.trim()) return;
    sending.value = true;
    try {
        const { data } = await api.post(`/threads/${route.params.id}/messages`, { body: body.value });
        messages.value.push(data);
        body.value = '';
        scrollDown();
    } catch (e) {
        toast(errorText(e), 'error');
    } finally {
        sending.value = false;
    }
}

async function toggleMute() {
    muted.value = !muted.value;
    await api.post(`/threads/${route.params.id}/read`, { muted: muted.value });
    toast(muted.value ? 'Уведомления темы выключены' : 'Уведомления темы включены');
}

async function togglePin() {
    const { data } = await api.patch(`/threads/${thread.value.id}`, { is_pinned: !thread.value.is_pinned });
    thread.value.is_pinned = data.is_pinned;
}

async function removeMessage(m) {
    if (!confirm('Удалить сообщение?')) return;
    await api.delete(`/threads/${thread.value.id}/messages/${m.id}`);
    messages.value = messages.value.filter((x) => x.id !== m.id);
}

function onKey(e) {
    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) send();
}

function showDay(i) {
    if (i === 0) return true;
    return new Date(messages.value[i].created_at).toDateString() !== new Date(messages.value[i - 1].created_at).toDateString();
}
</script>

<template>
    <div v-if="thread" class="flex flex-col">
        <div class="sticky top-14 z-10 -mx-4 mb-3 flex items-center gap-2 bg-slate-100/95 px-4 py-2 backdrop-blur md:top-0 md:-mx-8 md:px-8">
            <RouterLink to="/threads" class="btn-ghost px-3" aria-label="Назад к темам"><Icon name="arrow-left" /></RouterLink>
            <div class="min-w-0 flex-1">
                <h1 class="truncate text-xl font-bold">{{ thread.title }}</h1>
                <RouterLink v-if="thread.task" :to="`/tasks/${thread.task.id}`" class="text-sm font-semibold text-brand-700 underline">Открыть задачу</RouterLink>
                <RouterLink v-else-if="thread.trip" to="/travel" class="text-sm font-semibold text-brand-700 underline">Поездка: {{ thread.trip.title }}</RouterLink>
            </div>
            <button class="btn-ghost px-3" :aria-label="muted ? 'Включить уведомления' : 'Выключить уведомления'" :title="muted ? 'Включить уведомления' : 'Без уведомлений'" @click="toggleMute">
                <Icon :name="muted ? 'bell-off' : 'bell'" />
            </button>
            <button v-if="store.user.can_write" class="btn-ghost px-3" :aria-label="thread.is_pinned ? 'Открепить' : 'Закрепить'" @click="togglePin">
                <Icon name="pin" :class="thread.is_pinned ? 'text-brand-700' : ''" />
            </button>
        </div>

        <ol class="space-y-3" aria-live="polite">
            <template v-for="(m, i) in messages" :key="m.id">
                <li v-if="showDay(i)" class="py-2 text-center text-sm font-semibold text-slate-600">
                    {{ new Date(m.created_at).toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', weekday: 'long' }) }}
                </li>
                <li class="flex items-end gap-2" :class="m.user_id === store.user.id ? 'flex-row-reverse' : ''">
                    <Avatar :user="m.user" size="sm" />
                    <div class="group max-w-[80%] rounded-2xl px-4 py-2 shadow-sm"
                         :class="m.user_id === store.user.id ? 'rounded-br-sm bg-brand-700 text-white' : 'rounded-bl-sm bg-white'">
                        <div v-if="m.user_id !== store.user.id" class="text-sm font-bold" :style="{ color: m.user?.avatar_color }">{{ m.user?.name }}</div>
                        <p class="whitespace-pre-line break-words text-lg">{{ m.body }}</p>
                        <div class="flex items-center justify-end gap-2 text-xs opacity-75">
                            <button v-if="m.user_id === store.user.id || store.user.is_admin" class="hidden underline group-hover:inline" @click="removeMessage(m)">удалить</button>
                            <time :datetime="m.created_at" :title="ago(m.created_at)">{{ time(m.created_at) }}</time>
                        </div>
                    </div>
                </li>
            </template>
            <li v-if="!messages.length" class="py-8 text-center text-slate-600">Напишите первое сообщение.</li>
        </ol>
        <div ref="list" class="h-4" />

        <form v-if="store.user.can_write" class="sticky bottom-20 mt-2 flex items-end gap-2 rounded-2xl bg-white p-2 shadow-lg ring-1 ring-slate-200 md:bottom-4" @submit.prevent="send">
            <label for="msg" class="sr-only">Сообщение</label>
            <textarea id="msg" v-model="body" rows="1" class="field min-h-12 resize-none border-0" placeholder="Сообщение…" @keydown="onKey" />
            <button class="btn-primary px-4" :disabled="sending || !body.trim()" aria-label="Отправить"><Icon name="send" /></button>
        </form>
    </div>
</template>
