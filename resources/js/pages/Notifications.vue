<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '../api';
import EmptyState from '../components/EmptyState.vue';
import Icon from '../components/Icon.vue';
import { ago } from '../format';
import { refreshCounts } from '../store';

const router = useRouter();
const items = ref([]);

const CHANNEL_ICONS = { logistics: 'plane', projects: 'list-checks', maintenance: 'wrench', chat: 'message-circle', family: 'users' };

async function load() {
    items.value = (await api.get('/notifications')).data;
}

onMounted(load);

async function open(n) {
    if (!n.read_at) await api.post('/notifications/read', { ids: [n.id] });
    refreshCounts();
    if (n.url) router.push(n.url);
}

async function readAll() {
    await api.post('/notifications/read');
    await load();
    refreshCounts();
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="page-title">{{ $t('Уведомления') }}</h1>
            <div class="flex gap-2">
                <RouterLink to="/settings#notifications" class="btn-secondary"><Icon name="settings" /> {{ $t('Настроить') }}</RouterLink>
                <button v-if="items.some((n) => !n.read_at)" class="btn-secondary" @click="readAll"><Icon name="check" /> {{ $t('Прочитать все') }}</button>
            </div>
        </div>
        <ul class="space-y-2">
            <li v-for="n in items" :key="n.id">
                <button class="card flex w-full items-center gap-3 text-left hover:ring-2 hover:ring-brand-600"
                        :class="[!n.read_at ? 'ring-2 ring-brand-600' : 'opacity-80', n.urgent && !n.read_at ? '!bg-amber-50 !ring-amber-500' : '']"
                        @click="open(n)">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl"
                          :class="n.urgent ? 'bg-amber-400 text-black' : 'bg-slate-100 text-slate-700'">
                        <Icon :name="n.urgent ? 'bell-ring' : CHANNEL_ICONS[n.channel]" size="1.5em" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-lg" :class="!n.read_at ? 'font-bold' : 'font-semibold'">{{ n.title }}</span>
                        <span v-if="n.body" class="block truncate text-slate-700">{{ n.body }}</span>
                        <span class="block text-sm text-slate-600">{{ ago(n.created_at) }}</span>
                    </span>
                </button>
            </li>
        </ul>
        <EmptyState v-if="!items.length" icon="bell" :text="$t('Уведомлений нет')" />
    </div>
</template>
