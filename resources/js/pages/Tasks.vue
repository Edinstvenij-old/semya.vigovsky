<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { api, errorText } from '../api';
import Avatar from '../components/Avatar.vue';
import EmptyState from '../components/EmptyState.vue';
import Icon from '../components/Icon.vue';
import Modal from '../components/Modal.vue';
import TaskForm from '../components/TaskForm.vue';
import { date, daysUntil, STATUS_LABELS } from '../format';
import { onLive } from '../live';
import { store, toast } from '../store';

const router = useRouter();
const tasks = ref([]);
const categories = ref([]);
const category = ref('');
const onlyMine = ref(false);
const search = ref('');
const mobileStatus = ref('todo');
const creating = ref(false);
const managingCategories = ref(false);
const newCategory = ref('');
const dragged = ref(null);
const loading = ref(true);

const statuses = Object.keys(STATUS_LABELS);
const columnColors = { todo: 'bg-slate-200', in_progress: 'bg-amber-100', done: 'bg-emerald-100' };

async function load() {
    const params = { category_id: category.value || undefined, assignee: onlyMine.value ? 'me' : undefined, q: search.value || undefined };
    const [t, c] = await Promise.all([api.get('/tasks', { params }), api.get('/categories')]);
    tasks.value = t.data;
    categories.value = c.data;
    loading.value = false;
}

let off;
onMounted(() => {
    load();
    off = onLive('task', load);
});
onBeforeUnmount(() => off?.());
watch([category, onlyMine], load);
let searchTimer;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(load, 300);
});

const columns = computed(() =>
    Object.fromEntries(statuses.map((s) => [s, tasks.value.filter((t) => t.status === s)])),
);

async function move(task, status) {
    const previous = task.status;
    task.status = status;
    const order = columns.value[status].map((t) => t.id);
    try {
        await api.post(`/tasks/${task.id}/move`, { status, order });
        if (status === 'done') toast('Готово! Задача выполнена');
    } catch (e) {
        task.status = previous;
        toast(errorText(e), 'error');
    }
}

function onDrop(status) {
    if (dragged.value && dragged.value.status !== status) move(dragged.value, status);
    dragged.value = null;
}

function nextStatus(s) {
    return statuses[statuses.indexOf(s) + 1];
}

async function addCategory() {
    if (!newCategory.value.trim()) return;
    await api.post('/categories', { name: newCategory.value });
    newCategory.value = '';
    load();
}

async function removeCategory(c) {
    if (!confirm(`Удалить категорию «${c.name}»? Задачи останутся без категории.`)) return;
    await api.delete(`/categories/${c.id}`);
    if (category.value === c.id) category.value = '';
    load();
}

function onSaved(task) {
    creating.value = false;
    toast('Задача создана');
    router.push(`/tasks/${task.id}`);
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="page-title">Задачи и проекты</h1>
            <button v-if="store.user.can_write" class="btn-primary" @click="creating = true"><Icon name="plus" /> Новая задача</button>
        </div>

        <!-- Category filter -->
        <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1" role="tablist" aria-label="Категории">
            <button class="btn shrink-0" :class="category === '' ? 'bg-slate-900 text-white' : 'bg-white ring-2 ring-slate-300'"
                    role="tab" :aria-selected="category === ''" @click="category = ''">Все</button>
            <button v-for="c in categories" :key="c.id" class="btn shrink-0"
                    :class="category === c.id ? 'text-white' : 'bg-white ring-2 ring-slate-300'"
                    :style="category === c.id ? { background: c.color } : {}"
                    role="tab" :aria-selected="category === c.id" @click="category = c.id">
                <Icon :name="c.icon" /> {{ c.name }}
                <span v-if="c.open_count" class="rounded-full bg-black/10 px-2 text-sm">{{ c.open_count }}</span>
            </button>
            <button v-if="store.user.can_write" class="btn-ghost shrink-0" aria-label="Настроить категории" @click="managingCategories = true">
                <Icon name="settings" />
            </button>
        </div>

        <div class="flex flex-wrap gap-3">
            <label class="relative min-w-0 flex-1">
                <span class="sr-only">Поиск</span>
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500"><Icon name="search" /></span>
                <input v-model="search" class="field pl-11" placeholder="Поиск по задачам">
            </label>
            <label class="flex min-h-12 items-center gap-2 rounded-xl bg-white px-4 font-semibold ring-2 ring-slate-300">
                <input v-model="onlyMine" type="checkbox" class="h-5 w-5"> Только мои
            </label>
        </div>

        <!-- Mobile: one column at a time -->
        <div class="grid grid-cols-3 gap-1 rounded-2xl bg-slate-200 p-1 md:hidden" role="tablist">
            <button v-for="s in statuses" :key="s" class="min-h-12 rounded-xl font-semibold"
                    :class="mobileStatus === s ? 'bg-white shadow' : 'text-slate-700'"
                    role="tab" :aria-selected="mobileStatus === s" @click="mobileStatus = s">
                {{ STATUS_LABELS[s] }} <span class="text-slate-500">{{ columns[s].length }}</span>
            </button>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <section v-for="s in statuses" :key="s"
                     class="rounded-2xl p-3" :class="[columnColors[s], mobileStatus === s ? '' : 'hidden md:block']"
                     :aria-label="STATUS_LABELS[s]"
                     @dragover.prevent @drop="onDrop(s)">
                <h2 class="mb-3 hidden items-center justify-between px-1 text-lg font-bold md:flex">
                    {{ STATUS_LABELS[s] }} <span class="chip bg-white">{{ columns[s].length }}</span>
                </h2>
                <div class="space-y-3">
                    <article v-for="t in columns[s]" :key="t.id"
                             class="card cursor-pointer p-3 hover:ring-2 hover:ring-brand-600"
                             :draggable="store.user.can_write"
                             @dragstart="dragged = t"
                             @click="router.push(`/tasks/${t.id}`)">
                        <div class="flex items-start gap-2">
                            <h3 class="flex-1 font-bold" :class="t.status === 'done' ? 'text-slate-500 line-through' : ''">{{ t.title }}</h3>
                            <Avatar v-if="t.assignee" :user="t.assignee" size="sm" />
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-700">
                            <span v-if="t.priority === 'high'" class="chip bg-red-100 text-red-800">Срочно</span>
                            <span v-if="t.category" class="chip text-white" :style="{ background: t.category.color }">{{ t.category.name }}</span>
                            <span v-if="t.checklist_count" class="chip bg-slate-100"><Icon name="list-checks" /> {{ t.checklist_done_count }}/{{ t.checklist_count }}</span>
                            <span v-if="t.attachments_count" class="chip bg-slate-100"><Icon name="camera" /> {{ t.attachments_count }}</span>
                            <span v-if="t.due_date" class="chip" :class="daysUntil(t.due_date) < 0 && t.status !== 'done' ? 'bg-red-100 text-red-800' : 'bg-slate-100'">
                                <Icon name="clock" /> {{ date(t.due_date) }}
                            </span>
                        </div>
                        <button v-if="store.user.can_write && nextStatus(t.status)" class="btn-secondary mt-3 w-full md:hidden"
                                @click.stop="move(t, nextStatus(t.status))">
                            → {{ STATUS_LABELS[nextStatus(t.status)] }}
                        </button>
                    </article>
                    <EmptyState v-if="!loading && !columns[s].length" icon="clipboard-list" text="Пусто" />
                </div>
            </section>
        </div>

        <TaskForm v-if="creating" :categories="categories" :default-category="category || ''" @close="creating = false" @saved="onSaved" />

        <Modal v-if="managingCategories" title="Категории" @close="managingCategories = false">
            <ul class="mb-4 space-y-2">
                <li v-for="c in categories" :key="c.id" class="flex items-center gap-3">
                    <span class="h-4 w-4 rounded-full" :style="{ background: c.color }" />
                    <span class="flex-1 font-semibold">{{ c.name }}</span>
                    <button class="btn-ghost px-3" :aria-label="`Удалить ${c.name}`" @click="removeCategory(c)"><Icon name="trash" /></button>
                </li>
            </ul>
            <form class="flex gap-2" @submit.prevent="addCategory">
                <input v-model="newCategory" class="field" placeholder="Новая категория" aria-label="Название категории">
                <button class="btn-primary"><Icon name="plus" /></button>
            </form>
        </Modal>
    </div>
</template>
