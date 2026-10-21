<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { api, errorText, fieldErrors } from '../api';
import Avatar from '../components/Avatar.vue';
import EmptyState from '../components/EmptyState.vue';
import Icon from '../components/Icon.vue';
import Modal from '../components/Modal.vue';
import { date, daysUntil, dueText, MAINTENANCE_KINDS, money, monthYear, num } from '../format';
import { loadMembers, store, toast } from '../store';
import { t } from '../i18n';

const router = useRouter();
const tab = ref('list');
const items = ref([]);
const editing = ref(null); // {} for new
const completing = ref(null);
const form = reactive({});
const done = reactive({});
const errors = ref({});
const month = ref(new Date().toISOString().slice(0, 7));
const calendar = ref({ events: [], from: null, to: null });

const KIND_ICONS = { consumable: 'droplets', bill: 'zap', check: 'wrench', other: 'calendar-days' };
const PRESETS = [
    { title: t('Оплатить электроэнергию (день/ночь)'), kind: 'bill', interval_days: 30 },
    { title: t('Заменить фильтр воды'), kind: 'consumable', interval_days: 90 },
    { title: t('Проверить фильтр кондиционера'), kind: 'check', interval_days: 60 },
    { title: t('Заменить батарейки в датчиках дыма'), kind: 'consumable', interval_days: 365 },
];

async function load() {
    items.value = (await api.get('/maintenance')).data;
}

async function loadCalendar() {
    calendar.value = (await api.get('/calendar', { params: { month: month.value } })).data;
}

onMounted(() => {
    load();
    loadMembers();
});
watch([tab, month], () => tab.value === 'calendar' && loadCalendar());

function openEdit(item = null) {
    errors.value = {};
    Object.assign(form, {
        title: item?.title || '',
        description: item?.description || '',
        kind: item?.kind || 'consumable',
        interval_days: item?.interval_days || 30,
        next_due_on: item?.next_due_on || new Date().toISOString().slice(0, 10),
        remind_days_before: item?.remind_days_before ?? 3,
        assignee_id: item?.assignee_id ?? '',
    });
    editing.value = item || {};
}

function applyPreset(p) {
    Object.assign(form, p);
}

async function save() {
    errors.value = {};
    const payload = { ...form, assignee_id: form.assignee_id || null };
    try {
        if (editing.value.id) await api.patch(`/maintenance/${editing.value.id}`, payload);
        else await api.post('/maintenance', payload);
        editing.value = null;
        toast(t('Сохранено'));
        load();
    } catch (e) {
        errors.value = fieldErrors(e);
    }
}

async function remove() {
    if (!confirm(t('Удалить это регулярное дело вместе с историей?'))) return;
    await api.delete(`/maintenance/${editing.value.id}`);
    editing.value = null;
    load();
}

function openComplete(item) {
    Object.keys(done).forEach((k) => delete done[k]);
    Object.assign(done, { done_on: new Date().toISOString().slice(0, 10), note: '', amount: '', reading_day: '', reading_night: '' });
    completing.value = item;
}

async function complete() {
    try {
        await api.post(`/maintenance/${completing.value.id}/complete`, done);
        toast(t('Отмечено! Следующий срок пересчитан'));
        completing.value = null;
        load();
    } catch (e) {
        toast(errorText(e), 'error');
    }
}

// --- Month grid --------------------------------------------------------------
const weeks = computed(() => {
    if (!calendar.value.from) return [];
    const start = new Date(calendar.value.from + 'T00:00:00');
    const end = new Date(calendar.value.to + 'T00:00:00');
    const byDate = {};
    calendar.value.events.forEach((e) => (byDate[e.date] ||= []).push(e));
    const days = [];
    for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
        const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        days.push({ key, day: d.getDate(), inMonth: key.startsWith(month.value), today: key === new Date().toISOString().slice(0, 10), events: byDate[key] || [] });
    }
    return Array.from({ length: days.length / 7 }, (_, i) => days.slice(i * 7, i * 7 + 7));
});

const monthLabel = computed(() => monthYear(month.value + '-01T00:00:00'));

function shiftMonth(delta) {
    const d = new Date(month.value + '-01T00:00:00');
    d.setMonth(d.getMonth() + delta);
    month.value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

const EVENT_STYLE = { maintenance: 'bg-purple-100 text-purple-900', task: 'bg-orange-100 text-orange-900', trip: 'bg-blue-100 text-blue-900' };
const agenda = computed(() => calendar.value.events.filter((e) => e.date.startsWith(month.value)));

/** Clicking an event opens what it is about. */
function openEvent(e) {
    if (e.type === 'task') router.push(`/tasks/${e.id}`);
    else if (e.type === 'trip') router.push('/travel');
    else {
        const item = items.value.find((i) => i.id === e.id);
        if (item && store.user.can_write) openEdit(item);
        else tab.value = 'list';
    }
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="page-title">{{ $t('Дом и обслуживание') }}</h1>
            <button v-if="store.user.can_write" class="btn-primary" @click="openEdit()"><Icon name="plus" /> {{ $t('Регулярное дело') }}</button>
        </div>

        <div class="grid grid-cols-2 gap-1 rounded-2xl bg-slate-200 p-1 sm:max-w-sm" role="tablist">
            <button class="min-h-12 rounded-xl font-semibold" :class="tab === 'list' ? 'bg-white shadow' : ''" role="tab" :aria-selected="tab === 'list'" @click="tab = 'list'">
                {{ $t('Список') }}
            </button>
            <button class="min-h-12 rounded-xl font-semibold" :class="tab === 'calendar' ? 'bg-white shadow' : ''" role="tab" :aria-selected="tab === 'calendar'" @click="tab = 'calendar'">
                {{ $t('Календарь') }}
            </button>
        </div>

        <!-- List -->
        <div v-if="tab === 'list'" class="grid gap-3 lg:grid-cols-2">
            <article v-for="item in items" :key="item.id" class="card flex flex-col gap-3"
                     :class="daysUntil(item.next_due_on) < 0 ? 'ring-2 ring-red-500' : ''">
                <div class="flex items-start gap-3">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-purple-100 text-purple-800">
                        <Icon :name="KIND_ICONS[item.kind]" size="1.5em" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-lg font-bold">{{ item.title }}</h2>
                        <p class="text-slate-600">{{ $t(':p0 · каждые :p1 дн.', { p0: MAINTENANCE_KINDS[item.kind], p1: item.interval_days }) }}</p>
                    </div>
                    <Avatar v-if="item.assignee" :user="item.assignee" size="sm" />
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="chip text-base" :class="daysUntil(item.next_due_on) < 0 ? 'bg-red-100 text-red-800' : daysUntil(item.next_due_on) <= item.remind_days_before ? 'bg-amber-100 text-amber-900' : 'bg-emerald-100 text-emerald-900'">
                        {{ date(item.next_due_on) }} — {{ dueText(item.next_due_on) }}
                    </span>
                </div>
                <div v-if="item.logs?.[0]" class="rounded-xl bg-slate-50 p-3 text-sm text-slate-700">
                    {{ $t('Последний раз: :p0', { p0: date(item.logs[0].done_on) }) }}<template v-if="item.logs[0].doer">, {{ item.logs[0].doer.name }}</template>
                    <template v-if="item.logs[0].amount"> · {{ money(item.logs[0].amount) }}</template>
                    <template v-if="item.logs[0].reading_day"> {{ $t('· день :p0', { p0: num(item.logs[0].reading_day) }) }}</template>
                    <template v-if="item.logs[0].reading_night"> {{ $t('/ ночь :p0', { p0: num(item.logs[0].reading_night) }) }}</template>
                </div>
                <div v-if="store.user.can_write" class="mt-auto flex gap-2">
                    <button class="btn-primary flex-1" @click="openComplete(item)"><Icon name="circle-check" /> {{ $t('Сделано') }}</button>
                    <button class="btn-secondary px-3" :aria-label="$t('Изменить')" @click="openEdit(item)"><Icon name="pencil" /></button>
                </div>
            </article>
            <EmptyState v-if="!items.length" icon="calendar-days" :text="$t('Добавьте регулярные дела: замена фильтров, оплата счетов, проверка оборудования.')" class="lg:col-span-2" />
        </div>

        <!-- Calendar -->
        <div v-else class="card">
            <div class="mb-4 flex items-center justify-between">
                <button class="btn-ghost px-3" :aria-label="$t('Предыдущий месяц')" @click="shiftMonth(-1)"><Icon name="chevron-left" /></button>
                <h2 class="text-xl font-bold">{{ monthLabel }}</h2>
                <button class="btn-ghost px-3" :aria-label="$t('Следующий месяц')" @click="shiftMonth(1)"><Icon name="chevron-right" /></button>
            </div>
            <div class="hidden md:block">
                <div class="grid grid-cols-7 gap-1 text-center text-sm font-bold text-slate-600">
                    <div v-for="d in [$t('Пн'), $t('Вт'), $t('Ср'), $t('Чт'), $t('Пт'), $t('Сб'), $t('Вс')]" :key="d">{{ d }}</div>
                </div>
                <div v-for="(week, i) in weeks" :key="i" class="mt-1 grid grid-cols-7 gap-1">
                    <div v-for="day in week" :key="day.key" class="min-h-24 rounded-lg p-1"
                         :class="[day.inMonth ? 'bg-slate-50' : 'bg-white text-slate-400', day.today ? 'ring-2 ring-brand-600' : '']">
                        <div class="text-sm font-bold">{{ day.day }}</div>
                        <!-- Wrap up to 3 lines instead of cutting to «Замени…»; full titles are also listed below. -->
                        <button v-for="(e, j) in day.events" :key="j" type="button"
                                class="mt-0.5 line-clamp-3 w-full break-words rounded px-1 text-left text-xs font-semibold leading-tight hover:ring-2 hover:ring-brand-600"
                                :class="[EVENT_STYLE[e.type], e.projected ? 'opacity-60' : '']" :title="e.title" @click="openEvent(e)">{{ e.title }}</button>
                    </div>
                </div>
            </div>
            <!-- Full-title list of the month: the only view on phones, under the grid on bigger screens -->
            <h3 class="mb-2 mt-6 hidden text-lg font-bold md:block">{{ $t('События месяца') }}</h3>
            <ul class="space-y-2">
                <li v-for="(e, i) in agenda" :key="i">
                    <button type="button" class="flex w-full items-center gap-3 rounded-xl p-2 text-left hover:ring-2 hover:ring-brand-600"
                            :class="[EVENT_STYLE[e.type], e.projected ? 'opacity-70' : '']" @click="openEvent(e)">
                        <span class="w-28 shrink-0 font-bold">{{ date(e.date) }}</span>
                        <span class="min-w-0 flex-1 font-semibold">{{ e.title }}</span>
                    </button>
                </li>
                <li v-if="!agenda.length" class="text-slate-600">{{ $t('В этом месяце событий нет.') }}</li>
            </ul>
            <div class="mt-4 flex flex-wrap gap-3 text-sm">
                <span class="chip bg-purple-100 text-purple-900">{{ $t('Обслуживание') }}</span>
                <span class="chip bg-orange-100 text-orange-900">{{ $t('Срок задачи') }}</span>
                <span class="chip bg-blue-100 text-blue-900">{{ $t('Поездка') }}</span>
            </div>
        </div>

        <!-- Create / edit -->
        <Modal v-if="editing" :title="editing.id ? $t('Изменить') : $t('Новое регулярное дело')" @close="editing = null">
            <div v-if="!editing.id" class="mb-4 flex flex-wrap gap-2">
                <button v-for="p in PRESETS" :key="p.title" class="chip bg-slate-100 py-2 hover:bg-slate-200" @click="applyPreset(p)">{{ p.title }}</button>
            </div>
            <form class="space-y-4" @submit.prevent="save">
                <div>
                    <label class="field-label" for="m-title">{{ $t('Название') }}</label>
                    <input id="m-title" v-model="form.title" class="field" required>
                    <p v-if="errors.title" class="field-error">{{ errors.title }}</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="field-label" for="m-kind">{{ $t('Тип') }}</label>
                        <select id="m-kind" v-model="form.kind" class="field">
                            <option v-for="(l, k) in MAINTENANCE_KINDS" :key="k" :value="k">{{ l }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="field-label" for="m-int">{{ $t('Повторять каждые (дней)') }}</label>
                        <input id="m-int" v-model.number="form.interval_days" type="number" min="1" class="field" required>
                    </div>
                    <div>
                        <label class="field-label" for="m-next">{{ $t('Следующий срок') }}</label>
                        <input id="m-next" v-model="form.next_due_on" type="date" class="field" required>
                    </div>
                    <div>
                        <label class="field-label" for="m-rem">{{ $t('Напомнить за (дней)') }}</label>
                        <input id="m-rem" v-model.number="form.remind_days_before" type="number" min="0" max="60" class="field">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="field-label" for="m-who">{{ $t('Ответственный') }}</label>
                        <select id="m-who" v-model="form.assignee_id" class="field">
                            <option value="">{{ $t('Вся семья') }}</option>
                            <option v-for="m in store.members.filter((m) => m.role !== 'viewer')" :key="m.id" :value="m.id">{{ m.name }}</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="field-label" for="m-desc">{{ $t('Заметки (модель фильтра, номер лицевого счёта…)') }}</label>
                    <textarea id="m-desc" v-model="form.description" class="field" rows="2" />
                </div>
                <button class="btn-primary w-full text-lg">{{ $t('Сохранить') }}</button>
                <button v-if="editing.id" type="button" class="btn-ghost w-full text-red-700" @click="remove"><Icon name="trash" /> {{ $t('Удалить') }}</button>
            </form>
        </Modal>

        <!-- Mark done -->
        <Modal v-if="completing" :title="completing.title" @close="completing = null">
            <form class="space-y-4" @submit.prevent="complete">
                <div>
                    <label class="field-label" for="d-on">{{ $t('Когда сделано') }}</label>
                    <input id="d-on" v-model="done.done_on" type="date" class="field">
                </div>
                <template v-if="completing.kind === 'bill'">
                    <div>
                        <label class="field-label" for="d-amount">{{ $t('Сумма, ₴') }}</label>
                        <input id="d-amount" v-model="done.amount" type="number" step="0.01" min="0" class="field" inputmode="decimal">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="field-label" for="d-day">{{ $t('Показание «день» (T1)') }}</label>
                            <input id="d-day" v-model="done.reading_day" type="number" step="0.01" min="0" class="field" inputmode="decimal">
                        </div>
                        <div>
                            <label class="field-label" for="d-night">{{ $t('Показание «ночь» (T2)') }}</label>
                            <input id="d-night" v-model="done.reading_night" type="number" step="0.01" min="0" class="field" inputmode="decimal">
                        </div>
                    </div>
                    <p v-if="completing.logs?.[0]?.reading_day" class="text-sm text-slate-600">
                        {{ $t('Прошлые показания: день :p0, ночь :p1', { p0: num(completing.logs[0].reading_day), p1: num(completing.logs[0].reading_night) }) }}
                    </p>
                </template>
                <div>
                    <label class="field-label" for="d-note">{{ $t('Комментарий') }}</label>
                    <input id="d-note" v-model="done.note" class="field">
                </div>
                <button class="btn-primary w-full text-lg"><Icon name="circle-check" /> {{ $t('Отметить выполненным') }}</button>
            </form>
        </Modal>
    </div>
</template>
