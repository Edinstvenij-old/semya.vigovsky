<script setup>
import { computed, onMounted, ref } from 'vue';
import { api } from '../api';
import CheckinButtons from '../components/CheckinButtons.vue';
import AirAlertCard from '../components/AirAlertCard.vue';
import EmptyState from '../components/EmptyState.vue';
import SafetyCheckCard from '../components/SafetyCheckCard.vue';
import Icon from '../components/Icon.vue';
import StatusCard from '../components/StatusCard.vue';
import { date, dueText, daysUntil } from '../format';
import { simpleMode, store } from '../store';

const data = ref(null);

const greeting = computed(() => {
    const h = new Date().getHours();
    if (h < 5) return 'Доброй ночи';
    if (h < 12) return 'Доброе утро';
    if (h < 18) return 'Добрый день';
    return 'Добрый вечер';
});

async function load() {
    data.value = (await api.get('/dashboard')).data;
}

onMounted(load);

const tiles = computed(() => [
    { to: '/travel', icon: 'plane', label: 'Кто где', color: 'bg-brand-700' },
    { to: '/threads', icon: 'message-circle', label: 'Сообщения', color: 'bg-emerald-700', badge: store.counts.unread_messages },
    { to: '/photos', icon: 'image', label: 'Фото', color: 'bg-sky-700' },
    { to: '/home', icon: 'calendar-days', label: 'Дом', color: 'bg-purple-700' },
    { to: '/shopping', icon: 'shopping-cart', label: 'Покупки', color: 'bg-teal-700', badge: store.counts.shopping_open },
    { to: '/tasks', icon: 'list-checks', label: 'Задачи', color: 'bg-orange-700' },
]);
</script>

<template>
    <div class="space-y-6">
        <h1 class="page-title">{{ greeting }}, {{ store.user.name.split(' ')[0] }}!</h1>

        <AirAlertCard />
        <SafetyCheckCard @changed="load" />

        <!-- Simple mode: four huge tiles -->
        <div v-if="simpleMode()" class="grid grid-cols-2 gap-4">
            <RouterLink v-for="t in tiles" :key="t.to" :to="t.to"
                        class="relative flex min-h-36 flex-col items-center justify-center gap-2 rounded-3xl p-4 text-center text-xl font-bold text-white shadow"
                        :class="t.color">
                <Icon :name="t.icon" size="2.5em" />
                {{ t.label }}
                <span v-if="t.badge" class="absolute right-3 top-3 rounded-full bg-amber-400 px-2.5 py-0.5 text-black">{{ t.badge }}</span>
            </RouterLink>
        </div>

        <section class="card" aria-labelledby="statuses">
            <h2 id="statuses" class="mb-3 flex items-center gap-2 text-xl font-bold"><Icon name="map-pin" /> Где все</h2>
            <div v-if="data?.statuses.length" class="divide-y divide-slate-200">
                <StatusCard v-for="c in data.statuses" :key="c.id" :checkin="c" class="py-3 first:pt-0 last:pb-0" />
            </div>
            <p v-else-if="data" class="text-slate-600">За последние две недели статусов не было.</p>
        </section>

        <section v-if="store.user.can_write" class="card" aria-labelledby="checkin">
            <h2 id="checkin" class="mb-3 flex items-center gap-2 text-xl font-bold"><Icon name="send" /> Быстрый статус</h2>
            <CheckinButtons @done="load" />
        </section>

        <div class="grid gap-6 lg:grid-cols-2 [&>*]:min-w-0">
            <section v-if="!simpleMode()" class="card" aria-labelledby="mytasks">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                    <h2 id="mytasks" class="flex items-center gap-2 text-xl font-bold"><Icon name="list-checks" /> Мои задачи</h2>
                    <RouterLink to="/tasks" class="font-semibold text-brand-700 underline">Все</RouterLink>
                </div>
                <ul v-if="data?.my_tasks.length" class="space-y-2">
                    <li v-for="t in data.my_tasks" :key="t.id">
                        <RouterLink :to="`/tasks/${t.id}`" class="flex min-h-12 items-center gap-3 rounded-xl p-2 hover:bg-slate-100">
                            <span class="h-3 w-3 shrink-0 rounded-full" :style="{ background: t.category?.color || '#64748b' }" />
                            <span class="min-w-0 flex-1 break-words font-semibold">{{ t.title }}</span>
                            <span v-if="t.due_date" class="shrink-0 text-sm" :class="daysUntil(t.due_date) < 0 ? 'font-bold text-red-700' : 'text-slate-600'">
                                {{ date(t.due_date) }}
                            </span>
                        </RouterLink>
                    </li>
                </ul>
                <EmptyState v-else-if="data" icon="circle-check" text="Назначенных задач нет" />
            </section>

            <section class="card" aria-labelledby="due">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                    <h2 id="due" class="flex items-center gap-2 text-xl font-bold"><Icon name="wrench" /> Скоро по дому</h2>
                    <RouterLink to="/home" class="font-semibold text-brand-700 underline">Календарь</RouterLink>
                </div>
                <ul v-if="data?.maintenance_due.length" class="space-y-2">
                    <li v-for="m in data.maintenance_due" :key="m.id" class="flex min-h-12 flex-wrap items-center gap-x-3 gap-y-1 rounded-xl p-2">
                        <Icon :name="m.kind === 'bill' ? 'zap' : m.kind === 'consumable' ? 'droplets' : 'wrench'" />
                        <span class="min-w-0 flex-1 break-words font-semibold">{{ m.title }}</span>
                        <span class="chip" :class="daysUntil(m.next_due_on) < 0 ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-900'">
                            {{ dueText(m.next_due_on) }}
                        </span>
                    </li>
                </ul>
                <EmptyState v-else-if="data" icon="circle-check" text="В ближайшие две недели ничего" />
            </section>
        </div>
    </div>
</template>
