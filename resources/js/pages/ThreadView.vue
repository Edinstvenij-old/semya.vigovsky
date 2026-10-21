<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api, errorText } from '../api';
import Avatar from '../components/Avatar.vue';
import Icon from '../components/Icon.vue';
import PhotoViewer from '../components/PhotoViewer.vue';
import { ago, time } from '../format';
import { appendImages } from '../images';
import { onLive } from '../live';
import { realtime } from '../realtime';
import { refreshCounts, store, toast } from '../store';
import { intlLocale, t } from '../i18n';

const route = useRoute();
const router = useRouter();
const thread = ref(null);
const messages = ref([]);
const body = ref('');
const files = ref([]);
const previews = ref([]);
const sending = ref(false);
const muted = ref(false);
const list = ref(null);
const viewer = ref(null); // { photos, index }
let timer;
let offLive;

const lastId = computed(() => messages.value.at(-1)?.id || 0);
const canSend = computed(() => !sending.value && (body.value.trim() || files.value.length));

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

let lastPoll = 0;
async function poll() {
    lastPoll = Date.now();
    const { data } = await api.get(`/threads/${route.params.id}/messages`, { params: { after: lastId.value } });
    if (data.messages.length) {
        messages.value.push(...data.messages.filter((m) => !messages.value.some((x) => x.id === m.id)));
        scrollDown();
        markRead();
    }
}

onMounted(() => {
    load().catch(() => router.replace('/threads'));
    // With a live connection new messages arrive by signal; polling is only a slow safety net.
    timer = setInterval(() => (!realtime.connected || Date.now() - lastPoll > 30000) && poll().catch(() => {}), 5000);
    offLive = onLive('message', (e) => Number(e?.thread_id) === Number(route.params.id) && poll().catch(() => {}));
});
onBeforeUnmount(() => {
    clearInterval(timer);
    offLive?.();
    previews.value.forEach(URL.revokeObjectURL);
});

function pickFiles(event) {
    previews.value.forEach(URL.revokeObjectURL);
    files.value = [...event.target.files].slice(0, 10);
    previews.value = files.value.map((f) => URL.createObjectURL(f));
    event.target.value = '';
}

function clearFiles() {
    previews.value.forEach(URL.revokeObjectURL);
    files.value = [];
    previews.value = [];
}

async function send() {
    if (!canSend.value) return;
    sending.value = true;
    try {
        let payload = { body: body.value };
        if (files.value.length) {
            payload = new FormData();
            payload.append('body', body.value);
            await appendImages(payload, files.value);
        }
        const { data } = await api.post(`/threads/${route.params.id}/messages`, payload);
        if (data.queued) {
            toast('Нет связи — сообщение отправится, когда появится интернет');
        } else {
            messages.value.push(data);
        }
        body.value = '';
        clearFiles();
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
    toast(muted.value ? t('Уведомления темы выключены') : t('Уведомления темы включены'));
}

async function togglePin() {
    const { data } = await api.patch(`/threads/${thread.value.id}`, { is_pinned: !thread.value.is_pinned });
    thread.value.is_pinned = data.is_pinned;
}

async function removeMessage(m) {
    if (!confirm(t('Удалить сообщение?'))) return;
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

function openPhoto(m, index) {
    viewer.value = { photos: m.attachments.filter((a) => a.is_image), index };
}
</script>

<template>
    <div v-if="thread" class="flex flex-col">
        <div class="sticky top-16 z-10 -mx-4 mb-3 flex items-center gap-2 bg-slate-100/95 px-4 py-2 backdrop-blur md:top-0 md:-mx-8 md:px-8">
            <RouterLink to="/threads" class="btn-ghost shrink-0 px-3" :aria-label="$t('Назад к темам')"><Icon name="arrow-left" /></RouterLink>
            <div class="min-w-0 flex-1">
                <h1 class="truncate text-xl font-bold">{{ thread.title }}</h1>
                <RouterLink v-if="thread.task" :to="`/tasks/${thread.task.id}`" class="text-sm font-semibold text-brand-700 underline">{{ $t('Открыть задачу') }}</RouterLink>
                <RouterLink v-else-if="thread.trip" to="/travel" class="text-sm font-semibold text-brand-700 underline">{{ $t('Поездка: :p0', { p0: thread.trip.title }) }}</RouterLink>
            </div>
            <button class="btn-ghost shrink-0 px-3" :aria-label="muted ? $t('Включить уведомления') : $t('Выключить уведомления')" :title="muted ? $t('Включить уведомления') : $t('Без уведомлений')" @click="toggleMute">
                <Icon :name="muted ? 'bell-off' : 'bell'" />
            </button>
            <button v-if="store.user.can_write" class="btn-ghost shrink-0 px-3" :aria-label="thread.is_pinned ? $t('Открепить') : $t('Закрепить')" @click="togglePin">
                <Icon name="pin" :class="thread.is_pinned ? 'text-brand-700' : ''" />
            </button>
        </div>

        <ol class="space-y-3" aria-live="polite">
            <template v-for="(m, i) in messages" :key="m.id">
                <li v-if="showDay(i)" class="py-2 text-center text-sm font-semibold text-slate-600">
                    {{ new Date(m.created_at).toLocaleDateString(intlLocale(), { day: 'numeric', month: 'long', weekday: 'long' }) }}
                </li>
                <li class="flex items-end gap-2" :class="m.user_id === store.user.id ? 'flex-row-reverse' : ''">
                    <Avatar :user="m.user" size="sm" />
                    <div class="max-w-[80%] rounded-2xl px-3 py-2 shadow-sm"
                         :class="m.user_id === store.user.id ? 'rounded-br-sm bg-brand-700 text-white' : 'rounded-bl-sm bg-white'">
                        <div v-if="m.user_id !== store.user.id" class="text-sm font-bold" :style="{ color: m.user?.avatar_color }">{{ m.user?.name }}</div>
                        <div v-if="m.attachments?.length" class="my-1 grid gap-1" :class="m.attachments.length > 1 ? 'grid-cols-2' : ''">
                            <button v-for="(a, j) in m.attachments.filter((x) => x.is_image)" :key="a.id"
                                    class="overflow-hidden rounded-xl bg-black/10" :aria-label="$t('Открыть фото :p0', { p0: j + 1 })" @click="openPhoto(m, j)">
                                <img :src="a.thumb_url" :alt="a.original_name" loading="lazy"
                                     class="w-full object-cover" :class="m.attachments.length > 1 ? 'aspect-square' : 'max-h-72'">
                            </button>
                        </div>
                        <p v-if="m.body" class="whitespace-pre-line text-lg">{{ m.body }}</p>
                        <div class="flex items-center justify-end gap-3 text-xs opacity-80">
                            <button v-if="m.user_id === store.user.id || store.user.is_admin" class="underline" @click="removeMessage(m)">{{ $t('удалить') }}</button>
                            <time :datetime="m.created_at" :title="ago(m.created_at)">{{ time(m.created_at) }}</time>
                        </div>
                    </div>
                </li>
            </template>
            <li v-if="!messages.length" class="py-8 text-center text-slate-600">{{ $t('Напишите первое сообщение.') }}</li>
        </ol>
        <div ref="list" class="h-4" />

        <form v-if="store.user.can_write" class="sticky bottom-20 mt-2 rounded-2xl bg-white p-2 shadow-lg ring-1 ring-slate-200 md:bottom-4" @submit.prevent="send">
            <div v-if="previews.length" class="mb-2 flex flex-wrap items-center gap-2">
                <img v-for="(src, i) in previews" :key="i" :src="src" alt="" class="h-16 w-16 rounded-lg object-cover">
                <button type="button" class="btn-ghost px-3" :aria-label="$t('Убрать фото')" @click="clearFiles"><Icon name="x" /></button>
            </div>
            <div class="flex items-end gap-2">
                <label class="btn-ghost shrink-0 cursor-pointer px-3" :title="$t('Прикрепить фото')">
                    <Icon name="camera" />
                    <span class="sr-only">{{ $t('Прикрепить фото') }}</span>
                    <input type="file" class="sr-only" accept="image/*" multiple @change="pickFiles">
                </label>
                <label for="msg" class="sr-only">{{ $t('Сообщение') }}</label>
                <textarea id="msg" v-model="body" rows="1" class="field min-h-12 resize-none border-0" :placeholder="$t('Сообщение…')" @keydown="onKey" />
                <button class="btn-primary shrink-0 px-4" :disabled="!canSend" :aria-label="$t('Отправить')"><Icon name="send" /></button>
            </div>
        </form>

        <PhotoViewer v-if="viewer" :photos="viewer.photos" :start="viewer.index" @close="viewer = null" />
    </div>
</template>
