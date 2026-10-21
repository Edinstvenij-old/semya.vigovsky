<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api, errorText } from '../api';
import Avatar from '../components/Avatar.vue';
import Icon from '../components/Icon.vue';
import Modal from '../components/Modal.vue';
import TaskForm from '../components/TaskForm.vue';
import { date, money, PRIORITY_LABELS, STATUS_LABELS } from '../format';
import { appendImages } from '../images';
import { store, toast } from '../store';

const route = useRoute();
const router = useRouter();
const task = ref(null);
const categories = ref([]);
const editing = ref(false);
const newItem = ref('');
const link = reactive({ url: '', title: '', price: '' });
const uploading = ref('');
const preview = ref(null);
const canWrite = computed(() => store.user.can_write);

const PHOTO_GROUPS = [
    { kind: 'before', label: 'Фото «до»' },
    { kind: 'after', label: 'Фото «после»' },
    { kind: 'other', label: 'Другие файлы' },
];

async function load() {
    const [t, c] = await Promise.all([api.get(`/tasks/${route.params.id}`), api.get('/categories')]);
    task.value = t.data;
    categories.value = c.data;
}

onMounted(() => load().catch(() => router.replace('/tasks')));

const progress = computed(() => {
    const list = task.value?.checklist || [];
    return list.length ? Math.round((list.filter((i) => i.is_done).length / list.length) * 100) : 0;
});

async function setStatus(status) {
    await api.patch(`/tasks/${task.value.id}`, { status });
    await load();
    if (status === 'done') toast('Задача выполнена!');
}

async function toggleItem(item) {
    if (!canWrite.value) return;
    item.is_done = !item.is_done;
    await api.patch(`/tasks/${task.value.id}/checklist/${item.id}`, { is_done: item.is_done });
}

async function addItem() {
    if (!newItem.value.trim()) return;
    const { data } = await api.post(`/tasks/${task.value.id}/checklist`, { text: newItem.value });
    task.value.checklist.push(data);
    newItem.value = '';
}

async function removeItem(item) {
    await api.delete(`/tasks/${task.value.id}/checklist/${item.id}`);
    task.value.checklist = task.value.checklist.filter((i) => i.id !== item.id);
}

async function addLink() {
    try {
        const { data } = await api.post(`/tasks/${task.value.id}/links`, { ...link, price: link.price || null });
        task.value.links.push(data);
        Object.assign(link, { url: '', title: '', price: '' });
    } catch (e) {
        toast(errorText(e), 'error');
    }
}

async function removeLink(l) {
    await api.delete(`/tasks/${task.value.id}/links/${l.id}`);
    task.value.links = task.value.links.filter((x) => x.id !== l.id);
}

async function upload(kind, event) {
    const files = [...event.target.files];
    if (!files.length) return;
    uploading.value = kind;
    const body = new FormData();
    body.append('kind', kind);
    try {
        await appendImages(body, files);
        await api.post(`/tasks/${task.value.id}/attachments`, body);
        await load();
        toast('Файлы загружены');
    } catch (e) {
        toast(errorText(e), 'error');
    } finally {
        uploading.value = '';
        event.target.value = '';
    }
}

async function removeAttachment(a) {
    if (!confirm('Удалить файл?')) return;
    await api.delete(`/attachments/${a.id}`);
    preview.value = null;
    await load();
}

async function discuss() {
    const { data } = await api.post(`/tasks/${task.value.id}/thread`);
    router.push(`/threads/${data.id}`);
}

async function destroy() {
    if (!confirm('Удалить задачу со всеми фото?')) return;
    await api.delete(`/tasks/${task.value.id}`);
    toast('Задача удалена');
    router.push('/tasks');
}

function onSaved() {
    editing.value = false;
    load();
}
</script>

<template>
    <div v-if="task" class="space-y-4">
        <RouterLink to="/tasks" class="btn-ghost -ml-3"><Icon name="arrow-left" /> Все задачи</RouterLink>

        <div class="card space-y-4">
            <div class="flex flex-wrap items-start gap-3">
                <h1 class="page-title flex-1">{{ task.title }}</h1>
                <div v-if="canWrite" class="flex gap-2">
                    <button class="btn-secondary" @click="editing = true"><Icon name="pencil" /> Изменить</button>
                    <button class="btn-ghost px-3" aria-label="Удалить задачу" @click="destroy"><Icon name="trash" /></button>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <span v-if="task.category" class="chip text-white" :style="{ background: task.category.color }"><Icon :name="task.category.icon" /> {{ task.category.name }}</span>
                <span v-if="task.priority !== 'normal'" class="chip" :class="task.priority === 'high' ? 'bg-red-100 text-red-800' : 'bg-slate-100'">{{ PRIORITY_LABELS[task.priority] }}</span>
                <span v-if="task.due_date" class="chip bg-slate-100"><Icon name="clock" /> до {{ date(task.due_date) }}</span>
                <span v-if="task.assignee" class="chip bg-slate-100"><Avatar :user="task.assignee" size="sm" class="!h-6 !w-6 !text-xs" /> {{ task.assignee.name }}</span>
            </div>

            <!-- Status switcher -->
            <div class="grid grid-cols-3 gap-2" role="group" aria-label="Статус">
                <button v-for="(label, s) in STATUS_LABELS" :key="s" class="btn" :disabled="!canWrite"
                        :class="task.status === s ? (s === 'done' ? 'bg-emerald-700 text-white' : 'bg-brand-700 text-white') : 'bg-white ring-2 ring-slate-300'"
                        :aria-pressed="task.status === s" @click="setStatus(s)">
                    <Icon v-if="task.status === s" name="check" /> {{ label }}
                </button>
            </div>

            <p v-if="task.description" class="whitespace-pre-line text-lg">{{ task.description }}</p>

            <button class="btn-secondary" @click="discuss"><Icon name="message-circle" /> Обсудить в чате</button>
        </div>

        <!-- Checklist -->
        <section class="card" aria-labelledby="cl">
            <div class="mb-3 flex items-center justify-between">
                <h2 id="cl" class="flex items-center gap-2 text-xl font-bold"><Icon name="list-checks" /> Чек-лист</h2>
                <span v-if="task.checklist.length" class="font-semibold text-slate-600">{{ progress }}%</span>
            </div>
            <div v-if="task.checklist.length" class="mb-3 h-3 overflow-hidden rounded-full bg-slate-200" aria-hidden="true">
                <div class="h-full bg-emerald-600" :style="{ width: progress + '%' }" />
            </div>
            <ul class="space-y-1">
                <li v-for="item in task.checklist" :key="item.id" class="flex items-center gap-2">
                    <label class="flex min-h-12 flex-1 cursor-pointer items-center gap-3 rounded-xl px-2 hover:bg-slate-50">
                        <input type="checkbox" class="h-6 w-6 shrink-0" :checked="item.is_done" :disabled="!canWrite" @change="toggleItem(item)">
                        <span class="text-lg" :class="item.is_done ? 'text-slate-500 line-through' : ''">{{ item.text }}</span>
                    </label>
                    <button v-if="canWrite" class="btn-ghost px-3" :aria-label="`Удалить пункт ${item.text}`" @click="removeItem(item)"><Icon name="x" /></button>
                </li>
            </ul>
            <form v-if="canWrite" class="mt-3 flex gap-2" @submit.prevent="addItem">
                <input v-model="newItem" class="field" placeholder="Добавить пункт (например, спецификацию)" aria-label="Новый пункт">
                <button class="btn-primary" aria-label="Добавить"><Icon name="plus" /></button>
            </form>
        </section>

        <!-- Shop links -->
        <section class="card" aria-labelledby="links">
            <h2 id="links" class="mb-3 flex items-center gap-2 text-xl font-bold"><Icon name="shopping-cart" /> Ссылки на магазины</h2>
            <ul class="space-y-2">
                <li v-for="l in task.links" :key="l.id" class="flex items-center gap-2 rounded-xl bg-slate-50 p-2">
                    <a :href="l.url" target="_blank" rel="noopener noreferrer" class="flex min-h-12 flex-1 items-center gap-2 font-semibold text-brand-700 underline">
                        <Icon name="external-link" /> <span class="break-all">{{ l.title || l.url }}</span>
                    </a>
                    <span v-if="l.price" class="font-bold">{{ money(l.price) }}</span>
                    <button v-if="canWrite" class="btn-ghost px-3" aria-label="Удалить ссылку" @click="removeLink(l)"><Icon name="x" /></button>
                </li>
            </ul>
            <form v-if="canWrite" class="mt-3 grid gap-2 sm:grid-cols-[2fr_1fr_8rem_auto]" @submit.prevent="addLink">
                <input v-model="link.url" class="field" type="url" required placeholder="https://…" aria-label="Ссылка">
                <input v-model="link.title" class="field" placeholder="Название" aria-label="Название">
                <input v-model="link.price" class="field" type="number" step="0.01" min="0" placeholder="Цена" aria-label="Цена">
                <button class="btn-primary"><Icon name="plus" /> Добавить</button>
            </form>
        </section>

        <!-- Photos before / after -->
        <section class="card space-y-5" aria-labelledby="photos">
            <h2 id="photos" class="flex items-center gap-2 text-xl font-bold"><Icon name="camera" /> Фотографии и файлы</h2>
            <div v-for="g in PHOTO_GROUPS" :key="g.kind">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <h3 class="text-lg font-bold">{{ g.label }}</h3>
                    <label v-if="canWrite" class="btn-secondary cursor-pointer">
                        <Icon name="camera" /> {{ uploading === g.kind ? 'Загрузка…' : 'Добавить' }}
                        <input type="file" class="sr-only" multiple accept="image/*,application/pdf" @change="upload(g.kind, $event)">
                    </label>
                </div>
                <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-6">
                    <button v-for="a in task.attachments.filter((x) => x.kind === g.kind)" :key="a.id"
                            class="aspect-square overflow-hidden rounded-xl bg-slate-100 ring-1 ring-slate-200"
                            :aria-label="`Открыть ${a.original_name}`" @click="preview = a">
                        <img v-if="a.is_image" :src="a.thumb_url" :alt="a.original_name" class="h-full w-full object-cover" loading="lazy">
                        <span v-else class="flex h-full flex-col items-center justify-center gap-1 p-1 text-xs"><Icon name="file-text" size="2em" />{{ a.original_name }}</span>
                    </button>
                </div>
            </div>
        </section>

        <TaskForm v-if="editing" :task="task" :categories="categories" @close="editing = false" @saved="onSaved" />

        <Modal v-if="preview" :title="preview.original_name" wide @close="preview = null">
            <img v-if="preview.is_image" :src="preview.url" :alt="preview.original_name" class="mx-auto max-h-[65vh] rounded-xl">
            <div class="mt-4 flex flex-wrap gap-2">
                <a :href="preview.url" target="_blank" class="btn-secondary"><Icon name="download" /> Открыть / скачать</a>
                <button v-if="canWrite" class="btn-danger" @click="removeAttachment(preview)"><Icon name="trash" /> Удалить</button>
            </div>
        </Modal>
    </div>
</template>
