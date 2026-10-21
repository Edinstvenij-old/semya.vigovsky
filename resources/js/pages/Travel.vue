<script setup>
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api, fieldErrors } from '../api';
import Avatar from '../components/Avatar.vue';
import CheckinButtons from '../components/CheckinButtons.vue';
import EmptyState from '../components/EmptyState.vue';
import Icon from '../components/Icon.vue';
import Modal from '../components/Modal.vue';
import StatusCard from '../components/StatusCard.vue';
import { date, toLocalInput } from '../format';
import { onLive } from '../live';
import { loadMembers, store, toast } from '../store';

const router = useRouter();
const latest = ref([]);
const feed = ref([]);
const trips = ref([]);
const editingTrip = ref(null);
const tripForm = reactive({});
const errors = ref({});

async function load() {
    const [c, t] = await Promise.all([api.get('/checkins'), api.get('/trips')]);
    latest.value = c.data.latest;
    feed.value = c.data.feed;
    trips.value = t.data;
}

let off;
onMounted(() => {
    load();
    loadMembers();
    off = onLive('checkin', load);
});
onBeforeUnmount(() => off?.());

function openTrip(trip = null) {
    errors.value = {};
    Object.assign(tripForm, {
        title: trip?.title || '',
        destination: trip?.destination || '',
        starts_at: toLocalInput(trip?.starts_at),
        ends_at: toLocalInput(trip?.ends_at),
        traveller_ids: trip?.travellers?.map((u) => u.id) || [store.user.id],
    });
    editingTrip.value = trip || {};
}

async function saveTrip() {
    const payload = { ...tripForm, starts_at: tripForm.starts_at || null, ends_at: tripForm.ends_at || null };
    try {
        if (editingTrip.value.id) await api.patch(`/trips/${editingTrip.value.id}`, payload);
        else await api.post('/trips', payload);
        editingTrip.value = null;
        toast('Поездка сохранена');
        load();
    } catch (e) {
        errors.value = fieldErrors(e);
    }
}

async function removeTrip() {
    if (!confirm('Удалить поездку? Статусы и билеты останутся.')) return;
    await api.delete(`/trips/${editingTrip.value.id}`);
    editingTrip.value = null;
    load();
}

async function discussTrip(trip) {
    const { data } = await api.post('/threads', { title: trip.title, icon: 'plane', trip_id: trip.id });
    router.push(`/threads/${data.id}`);
}

function isActive(trip) {
    const now = Date.now();
    return trip.starts_at && new Date(trip.starts_at) <= now && (!trip.ends_at || new Date(trip.ends_at) >= now);
}
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="page-title">Поездки и перемещения</h1>
            <RouterLink to="/documents" class="btn-secondary"><Icon name="lock" /> Билеты и брони</RouterLink>
        </div>

        <section v-if="store.user.can_write" class="card" aria-labelledby="quick">
            <h2 id="quick" class="mb-3 text-xl font-bold">Я сейчас…</h2>
            <CheckinButtons :trips="trips" @done="load" />
        </section>

        <section class="card" aria-labelledby="now">
            <h2 id="now" class="mb-3 flex items-center gap-2 text-xl font-bold"><Icon name="users" /> Последние статусы</h2>
            <div v-if="latest.length" class="grid gap-4 sm:grid-cols-2">
                <StatusCard v-for="c in latest" :key="c.id" :checkin="c" class="rounded-xl bg-slate-50 p-3" />
            </div>
            <p v-else class="text-slate-600">Пока никто не отмечался.</p>
        </section>

        <section aria-labelledby="trips">
            <div class="mb-3 flex items-center justify-between">
                <h2 id="trips" class="flex items-center gap-2 text-xl font-bold"><Icon name="plane" /> Поездки</h2>
                <button v-if="store.user.can_write" class="btn-primary" @click="openTrip()"><Icon name="plus" /> Поездка</button>
            </div>
            <div class="grid gap-3 md:grid-cols-2">
                <article v-for="trip in trips" :key="trip.id" class="card" :class="isActive(trip) ? 'ring-2 ring-brand-600' : ''">
                    <div class="flex items-start gap-3">
                        <div class="flex-1">
                            <span v-if="isActive(trip)" class="chip mb-1 bg-brand-100 text-brand-800">Сейчас в пути</span>
                            <h3 class="text-lg font-bold">{{ trip.title }}</h3>
                            <p class="text-slate-600">
                                <template v-if="trip.destination">{{ trip.destination }} · </template>
                                {{ date(trip.starts_at) }}<template v-if="trip.ends_at"> — {{ date(trip.ends_at) }}</template>
                            </p>
                        </div>
                        <button v-if="store.user.can_write" class="btn-ghost px-3" aria-label="Изменить поездку" @click="openTrip(trip)"><Icon name="pencil" /></button>
                    </div>
                    <div class="mt-3 flex items-center gap-1">
                        <Avatar v-for="u in trip.travellers" :key="u.id" :user="u" size="sm" />
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <RouterLink :to="{ path: '/documents', query: { trip: trip.id } }" class="btn-secondary">
                            <Icon name="file-text" /> Билеты ({{ trip.documents_count }})
                        </RouterLink>
                        <button v-if="store.user.can_write" class="btn-ghost" @click="discussTrip(trip)"><Icon name="message-circle" /> Обсудить</button>
                    </div>
                </article>
            </div>
            <EmptyState v-if="!trips.length" icon="plane" text="Запланированных поездок нет" />
        </section>

        <section class="card" aria-labelledby="feed">
            <h2 id="feed" class="mb-3 flex items-center gap-2 text-xl font-bold"><Icon name="clock" /> Лента статусов</h2>
            <ol class="divide-y divide-slate-200">
                <li v-for="c in feed" :key="c.id" class="py-3"><StatusCard :checkin="c" /></li>
            </ol>
        </section>

        <Modal v-if="editingTrip" :title="editingTrip.id ? 'Изменить поездку' : 'Новая поездка'" @close="editingTrip = null">
            <form class="space-y-4" @submit.prevent="saveTrip">
                <div>
                    <label class="field-label" for="tr-title">Название</label>
                    <input id="tr-title" v-model="tripForm.title" class="field" required placeholder="Мама летит в Тбилиси">
                    <p v-if="errors.title" class="field-error">{{ errors.title }}</p>
                </div>
                <div>
                    <label class="field-label" for="tr-dest">Куда</label>
                    <input id="tr-dest" v-model="tripForm.destination" class="field">
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="field-label" for="tr-start">Начало</label>
                        <input id="tr-start" v-model="tripForm.starts_at" type="datetime-local" class="field">
                    </div>
                    <div>
                        <label class="field-label" for="tr-end">Окончание</label>
                        <input id="tr-end" v-model="tripForm.ends_at" type="datetime-local" class="field">
                        <p v-if="errors.ends_at" class="field-error">{{ errors.ends_at }}</p>
                    </div>
                </div>
                <fieldset>
                    <legend class="field-label">Кто едет</legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label v-for="m in store.members" :key="m.id" class="flex min-h-12 items-center gap-3 rounded-xl bg-slate-50 px-3">
                            <input v-model="tripForm.traveller_ids" type="checkbox" :value="m.id" class="h-5 w-5"> {{ m.name }}
                        </label>
                    </div>
                </fieldset>
                <button class="btn-primary w-full text-lg">Сохранить</button>
                <button v-if="editingTrip.id" type="button" class="btn-ghost w-full text-red-700" @click="removeTrip"><Icon name="trash" /> Удалить</button>
            </form>
        </Modal>
    </div>
</template>
