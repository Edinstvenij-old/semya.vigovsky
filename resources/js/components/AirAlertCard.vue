<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { api } from '../api';
import { ago } from '../format';
import Icon from './Icon.vue';

const data = ref(null);
let timer;

async function load() {
    try {
        data.value = (await api.get('/alerts')).data;
    } catch {
        /* keep the last known state */
    }
}

onMounted(() => {
    load();
    timer = setInterval(load, 60000);
});
onBeforeUnmount(() => clearInterval(timer));

const anyAlert = computed(() => data.value?.regions.some((r) => r.status === 'alert' || r.status === 'partial'));
</script>

<template>
    <section v-if="data?.configured && data.regions.length" class="card" :class="anyAlert ? '!bg-red-700 text-white ring-0' : ''"
             aria-labelledby="alerts-title" aria-live="polite">
        <h2 id="alerts-title" class="mb-2 flex items-center gap-2 text-xl font-bold">
            <Icon :name="anyAlert ? 'siren' : 'shield-check'" /> {{ anyAlert ? 'Тревога!' : 'Тревоги нет' }}
        </h2>
        <ul class="space-y-1">
            <li v-for="r in data.regions" :key="r.name" class="flex flex-wrap items-baseline gap-x-2">
                <b>{{ r.name }}:</b>
                <span v-if="r.status === 'alert'">{{ r.types.join(', ') }} · с {{ ago(r.since) }}</span>
                <span v-else-if="r.status === 'partial'">{{ r.types.join(', ') }} в части области — {{ r.places.join(', ') }}</span>
                <span v-else-if="r.status === 'calm'" :class="anyAlert ? 'opacity-90' : 'text-emerald-800'">спокойно</span>
                <span v-else class="opacity-80">нет данных (сервис оповещений недоступен)</span>
            </li>
        </ul>
        <p class="mt-2 text-sm" :class="anyAlert ? 'opacity-90' : 'text-slate-600'">Данные alerts.in.ua. Официальные сигналы — сирены и «Повітряна тривога».</p>
    </section>
</template>
