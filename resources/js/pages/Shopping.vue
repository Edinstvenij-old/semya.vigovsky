<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { api, errorText } from '../api';
import Avatar from '../components/Avatar.vue';
import EmptyState from '../components/EmptyState.vue';
import Icon from '../components/Icon.vue';
import { ago } from '../format';
import { onLive } from '../live';
import { store, toast } from '../store';

const open = ref([]);
const done = ref([]);
const text = ref('');
const quantity = ref('');
const showDone = ref(false);
let timer;
let offLive;

async function load() {
    const { data } = await api.get('/shopping');
    open.value = data.open;
    done.value = data.done;
}

onMounted(() => {
    load();
    // Someone else may be in the shop right now: keep the list fresh.
    timer = setInterval(() => load().catch(() => {}), 8000);
    offLive = onLive('shopping', load);
});
onBeforeUnmount(() => {
    clearInterval(timer);
    offLive?.();
});

async function add() {
    if (!text.value.trim()) return;
    try {
        const { data } = await api.post('/shopping', { text: text.value, quantity: quantity.value || null });
        if (!data.queued) open.value.unshift(data);
        text.value = '';
        quantity.value = '';
    } catch (e) {
        toast(errorText(e), 'error');
    }
}

async function toggle(item) {
    item.is_done = !item.is_done; // optimistic: the tick appears instantly in the shop
    try {
        await api.patch(`/shopping/${item.id}`, { is_done: item.is_done });
        await load();
    } catch (e) {
        item.is_done = !item.is_done;
        toast(errorText(e), 'error');
    }
}

async function remove(item) {
    await api.delete(`/shopping/${item.id}`);
    open.value = open.value.filter((i) => i.id !== item.id);
}

async function clearDone() {
    if (!confirm('Убрать все купленное из списка?')) return;
    await api.post('/shopping/clear-done');
    done.value = [];
}
</script>

<template>
    <div class="space-y-4">
        <h1 class="page-title">Список покупок</h1>

        <form v-if="store.user.can_write" class="card flex flex-wrap gap-2" @submit.prevent="add">
            <label class="min-w-0 flex-[3_1_12rem]">
                <span class="sr-only">Что купить</span>
                <input v-model="text" class="field" placeholder="Что купить? Например: хлеб" maxlength="200" enterkeyhint="done">
            </label>
            <label class="min-w-0 flex-[1_1_6rem]">
                <span class="sr-only">Сколько</span>
                <input v-model="quantity" class="field" placeholder="Сколько" maxlength="40">
            </label>
            <button class="btn-primary flex-[1_1_8rem]"><Icon name="plus" /> Добавить</button>
        </form>

        <ul v-if="open.length" class="space-y-2" aria-label="Нужно купить">
            <li v-for="item in open" :key="item.id" class="card flex items-center gap-3 p-2">
                <label class="flex min-h-14 flex-1 cursor-pointer items-center gap-3 px-2">
                    <input type="checkbox" class="h-8 w-8 shrink-0" :checked="item.is_done" :disabled="!store.user.can_write" @change="toggle(item)">
                    <span class="flex-1">
                        <span class="block text-lg font-semibold">{{ item.text }}<template v-if="item.quantity"> — {{ item.quantity }}</template></span>
                        <span class="block text-sm text-slate-600">{{ item.adder?.name }}, {{ ago(item.created_at) }}</span>
                    </span>
                </label>
                <button v-if="store.user.can_write" class="btn-ghost px-3" :aria-label="`Удалить ${item.text}`" @click="remove(item)"><Icon name="trash" /></button>
            </li>
        </ul>
        <EmptyState v-else icon="shopping-cart" text="Список пуст — всё куплено" />

        <section v-if="done.length" class="card">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <button class="btn-ghost -ml-3" :aria-expanded="showDone" @click="showDone = !showDone">
                    <Icon :name="showDone ? 'chevron-left' : 'chevron-right'" /> Куплено ({{ done.length }})
                </button>
                <button v-if="store.user.can_write" class="btn-ghost text-red-800" @click="clearDone"><Icon name="trash" /> Очистить</button>
            </div>
            <ul v-if="showDone" class="mt-2 space-y-1">
                <li v-for="item in done" :key="item.id" class="flex items-center gap-3 rounded-xl px-2 py-1">
                    <input type="checkbox" class="h-6 w-6 shrink-0" checked :disabled="!store.user.can_write" :aria-label="`Вернуть ${item.text} в список`" @change="toggle(item)">
                    <span class="flex-1 text-slate-600 line-through">{{ item.text }}<template v-if="item.quantity"> — {{ item.quantity }}</template></span>
                    <span class="text-sm text-slate-600">{{ item.doer?.name }}</span>
                </li>
            </ul>
        </section>
    </div>
</template>
