<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { api, errorText, fieldErrors } from '../api';
import Avatar from '../components/Avatar.vue';
import EmptyState from '../components/EmptyState.vue';
import Icon from '../components/Icon.vue';
import Modal from '../components/Modal.vue';
import TwoFactorSetup from '../components/TwoFactorSetup.vue';
import { dateTime, DOC_ICONS, DOC_TYPES, toLocalInput } from '../format';
import { appendImages } from '../images';
import { loadMembers, store, toast } from '../store';
import { t } from '../i18n';

const route = useRoute();
const state = ref('loading'); // loading | setup | challenge | ready
const docs = ref([]);
const trips = ref([]);
const search = ref('');
const upcoming = ref(true);
const tripFilter = ref(route.query.trip ? Number(route.query.trip) : '');
const code = ref('');
const codeError = ref('');
const editing = ref(null);
const form = reactive({});
const files = ref([]);
const errors = ref({});
const busy = ref(false);
const flight = reactive({}); // document id → status | 'loading' | error text

async function checkFlight(d) {
    flight[d.id] = 'loading';
    try {
        flight[d.id] = (await api.get(`/documents/${d.id}/flight-status`)).data;
    } catch (e) {
        flight[d.id] = errorText(e);
    }
}

async function load() {
    try {
        const { data } = await api.get('/documents', {
            params: { q: search.value || undefined, upcoming: upcoming.value ? 1 : undefined, trip_id: tripFilter.value || undefined },
        });
        docs.value = data;
        state.value = 'ready';
    } catch (e) {
        if (e.response?.status === 423) state.value = e.response.data.two_factor === 'setup_required' ? 'setup' : 'challenge';
        else toast(errorText(e), 'error');
    }
}

onMounted(async () => {
    await load();
    trips.value = (await api.get('/trips')).data;
    loadMembers();
});

let timer;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(load, 300);
});
watch([upcoming, tripFilter], load);

async function verify() {
    codeError.value = '';
    try {
        await api.post('/two-factor/verify', { code: code.value });
        code.value = '';
        load();
    } catch (e) {
        codeError.value = errorText(e);
    }
}

function openEdit(doc = null) {
    errors.value = {};
    files.value = [];
    Object.assign(form, {
        title: doc?.title || '',
        type: doc?.type || 'flight',
        carrier: doc?.carrier || '',
        reference: doc?.reference || '',
        booking_code: doc?.booking_code || '',
        from_place: doc?.from_place || '',
        to_place: doc?.to_place || '',
        departs_at: toLocalInput(doc?.departs_at),
        arrives_at: toLocalInput(doc?.arrives_at),
        notes: doc?.notes || '',
        trip_id: doc?.trip_id ?? tripFilter.value ?? '',
        traveller_id: doc?.traveller_id ?? store.user.id,
    });
    editing.value = doc || {};
}

async function save() {
    busy.value = true;
    errors.value = {};
    const body = new FormData();
    Object.entries(form).forEach(([k, v]) => body.append(k, v ?? ''));
    try {
        // Boarding passes keep more detail so barcodes stay scannable.
        await appendImages(body, files.value, { maxSide: 2560, quality: 0.9 });
        await api.post(editing.value.id ? `/documents/${editing.value.id}` : '/documents', body);
        editing.value = null;
        toast('Сохранено');
        load();
    } catch (e) {
        errors.value = fieldErrors(e);
        if (!Object.keys(errors.value).length) toast(errorText(e), 'error');
    } finally {
        busy.value = false;
    }
}

async function remove() {
    if (!confirm(t('Удалить документ и файлы?'))) return;
    await api.delete(`/documents/${editing.value.id}`);
    editing.value = null;
    load();
}

async function removeFile(a) {
    if (!confirm(t('Удалить файл :p0?', { p0: a.original_name }))) return;
    await api.delete(`/attachments/${a.id}`);
    editing.value.attachments = editing.value.attachments.filter((x) => x.id !== a.id);
    load();
}

function copy(text) {
    navigator.clipboard?.writeText(text);
    toast(t('Скопировано: :p0', { p0: text }));
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="page-title flex items-center gap-2"><Icon name="lock" /> {{ $t('Билеты и брони') }}</h1>
            <button v-if="state === 'ready' && store.user.can_write" class="btn-primary" @click="openEdit()"><Icon name="plus" /> {{ $t('Добавить') }}</button>
        </div>

        <p v-if="state === 'loading'" class="text-slate-600">{{ $t('Загрузка…') }}</p>

        <div v-else-if="state === 'setup'" class="card mx-auto max-w-lg">
            <h2 class="mb-3 text-xl font-bold">{{ $t('Включите двухфакторную защиту') }}</h2>
            <TwoFactorSetup @enabled="load" />
        </div>

        <form v-else-if="state === 'challenge'" class="card mx-auto max-w-md space-y-4" @submit.prevent="verify">
            <h2 class="flex items-center gap-2 text-xl font-bold"><Icon name="key-round" /> {{ $t('Подтвердите вход') }}</h2>
            <p>{{ $t('Раздел с документами защищён. Введите код из приложения-аутентификатора или резервный код.') }}</p>
            <input v-model="code" class="field text-center text-2xl tracking-widest" inputmode="numeric" autocomplete="one-time-code"
                   :aria-label="$t('Код подтверждения')" required>
            <p v-if="codeError" class="field-error" role="alert">{{ codeError }}</p>
            <button class="btn-primary w-full text-lg">{{ $t('Открыть документы') }}</button>
        </form>

        <template v-else>
            <div class="flex flex-wrap gap-3">
                <label class="relative min-w-0 flex-1 basis-64">
                    <span class="sr-only">{{ $t('Поиск') }}</span>
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500"><Icon name="search" /></span>
                    <input v-model="search" class="field pl-11" :placeholder="$t('Номер рейса, бронь, город…')">
                </label>
                <select v-model="tripFilter" class="field w-auto" :aria-label="$t('Поездка')">
                    <option value="">{{ $t('Все поездки') }}</option>
                    <option v-for="t in trips" :key="t.id" :value="t.id">{{ t.title }}</option>
                </select>
                <label class="flex min-h-12 items-center gap-2 rounded-xl bg-white px-4 font-semibold ring-2 ring-slate-300">
                    <input v-model="upcoming" type="checkbox" class="h-5 w-5"> {{ $t('Только предстоящие') }}
                </label>
            </div>

            <div class="grid gap-3 lg:grid-cols-2">
                <article v-for="d in docs" :key="d.id" class="card space-y-3">
                    <div class="flex items-start gap-3">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-brand-100 text-brand-800">
                            <Icon :name="DOC_ICONS[d.type]" size="1.5em" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <h2 class="text-lg font-bold">{{ d.title }}</h2>
                            <p class="text-slate-600">{{ DOC_TYPES[d.type] }}<template v-if="d.carrier"> · {{ d.carrier }}</template></p>
                        </div>
                        <Avatar v-if="d.traveller" :user="d.traveller" size="sm" />
                        <button v-if="store.user.can_write" class="btn-ghost px-3" :aria-label="$t('Изменить')" @click="openEdit(d)"><Icon name="pencil" /></button>
                    </div>

                    <div v-if="d.from_place || d.to_place" class="flex items-center gap-2 text-xl font-bold">
                        {{ d.from_place }} <Icon name="chevron-right" /> {{ d.to_place }}
                    </div>

                    <dl class="grid grid-cols-2 gap-2">
                        <div v-if="d.reference" class="rounded-xl bg-slate-50 p-2">
                            <dt class="text-sm text-slate-600">{{ $t('Рейс / номер') }}</dt>
                            <dd><button class="text-xl font-bold tracking-wide" @click="copy(d.reference)">{{ d.reference }}</button></dd>
                        </div>
                        <div v-if="d.booking_code" class="rounded-xl bg-slate-50 p-2">
                            <dt class="text-sm text-slate-600">{{ $t('Код брони') }}</dt>
                            <dd><button class="text-xl font-bold tracking-wide" @click="copy(d.booking_code)">{{ d.booking_code }}</button></dd>
                        </div>
                        <div v-if="d.departs_at" class="rounded-xl bg-slate-50 p-2">
                            <dt class="text-sm text-slate-600">{{ $t('Отправление') }}</dt>
                            <dd class="font-bold">{{ dateTime(d.departs_at) }}</dd>
                        </div>
                        <div v-if="d.arrives_at" class="rounded-xl bg-slate-50 p-2">
                            <dt class="text-sm text-slate-600">{{ $t('Прибытие') }}</dt>
                            <dd class="font-bold">{{ dateTime(d.arrives_at) }}</dd>
                        </div>
                    </dl>
                    <p v-if="d.notes" class="whitespace-pre-line text-slate-700">{{ d.notes }}</p>
                    <div v-if="d.attachments.length" class="flex flex-wrap gap-2">
                        <a v-for="a in d.attachments" :key="a.id" :href="a.url" target="_blank" class="btn-secondary">
                            <Icon :name="a.is_image ? 'image' : 'file-text'" /> {{ a.original_name }}
                        </a>
                    </div>
                    <div v-if="d.type === 'flight' && d.reference && store.config.flight_status_enabled">
                        <button class="btn-secondary" :disabled="flight[d.id] === 'loading'" @click="checkFlight(d)">
                            <Icon name="refresh-cw" /> {{ flight[d.id] === 'loading' ? $t('Проверяем…') : $t('Статус рейса') }}
                        </button>
                        <div v-if="typeof flight[d.id] === 'object'" class="mt-2 space-y-1 rounded-xl p-3" role="status"
                             :class="flight[d.id].status === 'Canceled' ? 'bg-red-100 text-red-900' : flight[d.id].delay_minutes ? 'bg-amber-100 text-amber-950' : 'bg-emerald-50 text-emerald-950'">
                            <p class="text-lg font-bold">{{ flight[d.id].status_label }}<template v-if="flight[d.id].delay_minutes"> {{ $t('· задержка :p0 мин', { p0: flight[d.id].delay_minutes }) }}</template></p>
                            <p>
                                {{ $t('Вылет :p0:', { p0: flight[d.id].departure.airport }) }}
                                <b>{{ dateTime(flight[d.id].departure.revised || flight[d.id].departure.scheduled) }}</b>
                                <template v-if="flight[d.id].departure.terminal"> {{ $t('· терминал :p0', { p0: flight[d.id].departure.terminal }) }}</template>
                                <template v-if="flight[d.id].departure.gate"> {{ $t('· выход :p0', { p0: flight[d.id].departure.gate }) }}</template>
                            </p>
                            <p>
                                {{ $t('Прилёт :p0:', { p0: flight[d.id].arrival.airport }) }}
                                <b>{{ dateTime(flight[d.id].arrival.revised || flight[d.id].arrival.scheduled) }}</b>
                                <template v-if="flight[d.id].arrival.baggage_belt"> {{ $t('· лента :p0', { p0: flight[d.id].arrival.baggage_belt }) }}</template>
                            </p>
                            <p class="text-sm opacity-80">{{ $t('Проверено :p0', { p0: dateTime(flight[d.id].checked_at) }) }}</p>
                        </div>
                        <p v-else-if="flight[d.id] && flight[d.id] !== 'loading'" class="field-error mt-2">{{ flight[d.id] }}</p>
                    </div>
                    <p v-if="d.trip" class="text-sm text-slate-600">{{ $t('Поездка: :p0', { p0: d.trip.title }) }}</p>
                </article>
            </div>
            <EmptyState v-if="!docs.length" icon="file-text" :text="$t('Документов нет. Загрузите посадочные талоны и брони — их увидит вся семья.')" />
        </template>

        <Modal v-if="editing" :title="editing.id ? $t('Изменить документ') : $t('Новый билет / бронь')" wide @close="editing = null">
            <form class="space-y-4" @submit.prevent="save">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="field-label" for="d-title">{{ $t('Название') }}</label>
                        <input id="d-title" v-model="form.title" class="field" required :placeholder="$t('Одесса — Кишинёв, мама')">
                        <p v-if="errors.title" class="field-error">{{ errors.title }}</p>
                    </div>
                    <div>
                        <label class="field-label" for="d-type">{{ $t('Тип') }}</label>
                        <select id="d-type" v-model="form.type" class="field">
                            <option v-for="(l, k) in DOC_TYPES" :key="k" :value="k">{{ l }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="field-label" for="d-carrier">{{ $t('Перевозчик / отель') }}</label>
                        <input id="d-carrier" v-model="form.carrier" class="field">
                    </div>
                    <div>
                        <label class="field-label" for="d-ref">{{ $t('Номер рейса / автобуса') }}</label>
                        <input id="d-ref" v-model="form.reference" class="field uppercase" placeholder="SU 1234">
                    </div>
                    <div>
                        <label class="field-label" for="d-code">{{ $t('Код брони') }}</label>
                        <input id="d-code" v-model="form.booking_code" class="field uppercase">
                    </div>
                    <div>
                        <label class="field-label" for="d-from">{{ $t('Откуда') }}</label>
                        <input id="d-from" v-model="form.from_place" class="field">
                    </div>
                    <div>
                        <label class="field-label" for="d-to">{{ $t('Куда (или пункт пересадки)') }}</label>
                        <input id="d-to" v-model="form.to_place" class="field">
                    </div>
                    <div>
                        <label class="field-label" for="d-dep">{{ $t('Отправление') }}</label>
                        <input id="d-dep" v-model="form.departs_at" type="datetime-local" class="field">
                    </div>
                    <div>
                        <label class="field-label" for="d-arr">{{ $t('Прибытие') }}</label>
                        <input id="d-arr" v-model="form.arrives_at" type="datetime-local" class="field">
                    </div>
                    <div>
                        <label class="field-label" for="d-who">{{ $t('Пассажир') }}</label>
                        <select id="d-who" v-model="form.traveller_id" class="field">
                            <option value="">—</option>
                            <option v-for="m in store.members" :key="m.id" :value="m.id">{{ m.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="field-label" for="d-trip">{{ $t('Поездка') }}</label>
                        <select id="d-trip" v-model="form.trip_id" class="field">
                            <option value="">—</option>
                            <option v-for="t in trips" :key="t.id" :value="t.id">{{ t.title }}</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="field-label" for="d-notes">{{ $t('Заметки (терминал, багаж, место)') }}</label>
                        <textarea id="d-notes" v-model="form.notes" class="field" rows="2" />
                    </div>
                </div>
                <div>
                    <span class="field-label">{{ $t('Файлы (PDF, фото, .pkpass)') }}</span>
                    <ul v-if="editing.attachments?.length" class="mb-2 space-y-1">
                        <li v-for="a in editing.attachments" :key="a.id" class="flex items-center gap-2">
                            <Icon name="paperclip" /> <span class="flex-1">{{ a.original_name }}</span>
                            <button type="button" class="btn-ghost px-3" :aria-label="$t('Удалить файл')" @click="removeFile(a)"><Icon name="x" /></button>
                        </li>
                    </ul>
                    <label class="btn-secondary cursor-pointer">
                        <Icon name="paperclip" /> {{ $t('Выбрать файлы') }}
                        <input type="file" multiple class="sr-only" accept="application/pdf,image/*,.pkpass" @change="files = [...$event.target.files]">
                    </label>
                    <p v-if="files.length" class="mt-1 text-sm">{{ $t('Выбрано: :p0', { p0: files.map((f) => f.name).join(', ') }) }}</p>
                    <p v-if="errors['files.0']" class="field-error">{{ errors['files.0'] }}</p>
                </div>
                <button class="btn-primary w-full text-lg" :disabled="busy">{{ busy ? $t('Сохранение…') : $t('Сохранить') }}</button>
                <button v-if="editing.id" type="button" class="btn-ghost w-full text-red-700" @click="remove"><Icon name="trash" /> {{ $t('Удалить') }}</button>
            </form>
        </Modal>
    </div>
</template>
