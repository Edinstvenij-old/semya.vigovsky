<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { api } from '../api';
import CheckinButtons from '../components/CheckinButtons.vue';
import AirAlertCard from '../components/AirAlertCard.vue';
import EmptyState from '../components/EmptyState.vue';
import SafetyCheckCard from '../components/SafetyCheckCard.vue';
import Icon from '../components/Icon.vue';
import StatusCard from '../components/StatusCard.vue';
import { date, dueText, daysUntil } from '../format';
import { onLive } from '../live';
import { simpleMode, store } from '../store';
import { t } from '../i18n';

const data = ref(null);

const greeting = computed(() => {
    const h = new Date().getHours();
    if (h < 5) return t('Доброй ночи');
    if (h < 12) return t('Доброе утро');
    if (h < 18) return t('Добрый день');
    return t('Добрый вечер');
});

async function load() {
    data.value = (await api.get('/dashboard')).data;
}

const offs = [];
onMounted(() => {
    load();
    offs.push(onLive('checkin', load), onLive('task', load));
});
onBeforeUnmount(() => offs.forEach((off) => off()));

const tiles = computed(() => [
    { to: '/travel', icon: 'plane', label: t('Кто где'), color: 'bg-brand-700' },
    { to: '/threads', icon: 'message-circle', label: t('Сообщения'), color: 'bg-emerald-700', badge: store.counts.unread_messages },
    { to: '/photos', icon: 'image', label: t('Фото'), color: 'bg-sky-700' },
    { to: '/home', icon: 'calendar-days', label: t('Дом'), color: 'bg-purple-700' },
    { to: '/shopping', icon: 'shopping-cart', label: t('Покупки'), color: 'bg-teal-700', badge: store.counts.shopping_open },
    { to: '/tasks', icon: 'list-checks', label: t('Задачи'), color: 'bg-orange-700' },
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
            <h2 id="statuses" class="mb-3 flex items-center gap-2 text-xl font-bold"><Icon name="map-pin" /> {{ $t('Где все') }}</h2>
            <div v-if="data?.statuses.length" class="divide-y divide-slate-200">
                <StatusCard v-for="c in data.statuses" :key="c.id" :checkin="c" class="py-3 first:pt-0 last:pb-0" />
            </div>
            <p v-else-if="data" class="text-slate-600">{{ $t('За последние две недели статусов не было.') }}</p>
        </section>

        <section v-if="store.user.can_write" class="card" aria-labelledby="checkin">
            <h2 id="checkin" class="mb-3 flex items-center gap-2 text-xl font-bold"><Icon name="send" /> {{ $t('Быстрый статус') }}</h2>
            <CheckinButtons @done="load" />
        </section>

        <div class="grid gap-6 lg:grid-cols-2 [&>*]:min-w-0">
            <section v-if="!simpleMode()" class="card" aria-labelledby="mytasks">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                    <h2 id="mytasks" class="flex items-center gap-2 text-xl font-bold"><Icon name="list-checks" /> {{ $t('Мои задачи') }}</h2>
                    <RouterLink to="/tasks" class="font-semibold text-brand-700 underline">{{ $t('Все') }}</RouterLink>
                </div>
                <ul v-if="data?.my_tasks.length" class="space-y-2">
                    <li v-for="t in data.my_tasks" :key="t.id">
                        <!-- Date under the title: in a half-width card a side-by-side date squeezed the title. -->
                        <RouterLink :to="`/tasks/${t.id}`" class="flex min-h-12 items-start gap-3 rounded-xl p-2 hover:bg-slate-100">
                            <span class="mt-2 h-3 w-3 shrink-0 rounded-full" :style="{ background: t.category?.color || '#64748b' }" />
                            <span class="min-w-0 flex-1">
                                <span class="block font-semibold">{{ t.title }}</span>
                                <span v-if="t.due_date" class="block text-sm" :class="daysUntil(t.due_date) < 0 ? 'font-bold text-red-700' : 'text-slate-600'">
                                    {{ $t('до :p0', { p0: date(t.due_date) }) }}
                                </span>
                            </span>
                        </RouterLink>
                    </li>
                </ul>
                <EmptyState v-else-if="data" icon="circle-check" :text="$t('Назначенных задач нет')" />
            </section>

            <section class="card" aria-labelledby="due">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                    <h2 id="due" class="flex items-center gap-2 text-xl font-bold"><Icon name="wrench" /> {{ $t('Скоро по дому') }}</h2>
                    <RouterLink to="/home" class="font-semibold text-brand-700 underline">{{ $t('Календарь') }}</RouterLink>
                </div>
                <ul v-if="data?.maintenance_due.length" class="space-y-2">
                    <li v-for="m in data.maintenance_due" :key="m.id">
                        <RouterLink to="/home" class="flex min-h-12 items-start gap-3 rounded-xl p-2 hover:bg-slate-100">
                            <Icon :name="m.kind === 'bill' ? 'zap' : m.kind === 'consumable' ? 'droplets' : 'wrench'" class="mt-0.5" />
                            <span class="min-w-0 flex-1">
                                <span class="block font-semibold">{{ m.title }}</span>
                                <span class="chip mt-1" :class="daysUntil(m.next_due_on) < 0 ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-900'">
                                    {{ dueText(m.next_due_on) }}
                                </span>
                            </span>
                        </RouterLink>
                    </li>
                </ul>
                <EmptyState v-else-if="data" icon="circle-check" :text="$t('В ближайшие две недели ничего')" />
            </section>
        </div>
    </div>
</template>
