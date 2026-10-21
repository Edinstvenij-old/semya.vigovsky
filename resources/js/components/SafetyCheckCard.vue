<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { api, errorText } from '../api';
import { ago } from '../format';
import { onLive } from '../live';
import { refreshCounts, store, toast } from '../store';
import Avatar from './Avatar.vue';
import Icon from './Icon.vue';
import Modal from './Modal.vue';
import { t } from '../i18n';

const emit = defineEmits(['changed']);
const check = ref(null);
const loaded = ref(false);
const asking = ref(false);
const askNote = ref('');
const busy = ref(false);
let timer;

const STATUS = {
    safe: { label: t('В безопасности'), icon: 'circle-check', cls: 'bg-emerald-100 text-emerald-900' },
    shelter: { label: t('В укрытии'), icon: 'shield', cls: 'bg-amber-100 text-amber-900' },
    help: { label: t('Нужна помощь'), icon: 'siren', cls: 'bg-red-600 text-white' },
};

const mine = computed(() => check.value?.members.find((m) => m.user.id === store.user.id));
const someoneNeedsHelp = computed(() => check.value?.members.some((m) => m.status === 'help'));

async function load() {
    try {
        check.value = (await api.get('/safety-checks/active')).data.check;
    } finally {
        loaded.value = true;
    }
}

let off;
onMounted(() => {
    load();
    timer = setInterval(() => load().catch(() => {}), 15000);
    off = onLive('safety', () => load().catch(() => {}));
});
onBeforeUnmount(() => {
    clearInterval(timer);
    off?.();
});

async function ask() {
    busy.value = true;
    try {
        check.value = (await api.post('/safety-checks', { note: askNote.value || null })).data.check;
        asking.value = false;
        askNote.value = '';
        toast('Вопрос отправлен всей семье');
    } catch (e) {
        toast(errorText(e), 'error');
    } finally {
        busy.value = false;
    }
}

async function respond(status) {
    busy.value = true;
    try {
        const { data } = await api.post(`/safety-checks/${check.value.id}/respond`, { status });
        if (data.queued) {
            mine.value.status = status; // offline: shown now, sent when the connection is back
        } else {
            check.value = data.check;
        }
        refreshCounts();
        emit('changed');
        toast(status === 'help' ? t('Семья получила сигнал «Нужна помощь»') : t('Ответ отправлен'));
    } catch (e) {
        toast(errorText(e), 'error');
    } finally {
        busy.value = false;
    }
}

async function close() {
    await api.post(`/safety-checks/${check.value.id}/close`);
    check.value = null;
    refreshCounts();
}
</script>

<template>
    <section v-if="check" class="card space-y-4 ring-2" :class="someoneNeedsHelp ? 'ring-red-600' : 'ring-amber-500'" aria-labelledby="safety-title" aria-live="polite">
        <div>
            <h2 id="safety-title" class="flex items-center gap-2 text-xl font-bold"><Icon name="shield" /> {{ $t('Все ли в порядке?') }}</h2>
            <p class="text-slate-600">
                {{ $t('Спросил(а) :p0, :p1', { p0: check.creator?.name, p1: ago(check.created_at) }) }}<template v-if="check.note"> — «{{ check.note }}»</template>
            </p>
        </div>

        <div v-if="mine && !mine.status" class="grid gap-3">
            <p class="text-lg font-bold">{{ $t('Ответьте, пожалуйста:') }}</p>
            <button class="btn min-h-16 bg-emerald-700 text-xl text-white hover:bg-emerald-800" :disabled="busy" @click="respond('safe')">
                <Icon name="circle-check" size="1.4em" /> {{ $t('Я в безопасности') }}
            </button>
            <button class="btn min-h-16 bg-amber-500 text-xl text-black hover:bg-amber-600" :disabled="busy" @click="respond('shelter')">
                <Icon name="shield" size="1.4em" /> {{ $t('Я в укрытии') }}
            </button>
            <button class="btn-danger min-h-16 text-xl" :disabled="busy" @click="respond('help')">
                <Icon name="siren" size="1.4em" /> {{ $t('Нужна помощь') }}
            </button>
        </div>

        <div>
            <p class="mb-2 font-semibold">{{ $t('Ответили :p0 из :p1', { p0: check.answered, p1: check.total }) }}</p>
            <ul class="space-y-2">
                <li v-for="m in check.members" :key="m.user.id" class="flex flex-wrap items-center gap-3 rounded-xl bg-slate-50 p-2">
                    <Avatar :user="m.user" size="sm" />
                    <span class="flex-1 font-semibold">{{ m.user.name }}</span>
                    <span v-if="m.status" class="chip" :class="STATUS[m.status].cls">
                        <Icon :name="STATUS[m.status].icon" /> {{ STATUS[m.status].label }}
                    </span>
                    <span v-else class="chip bg-slate-200 text-slate-800"><Icon name="clock" /> {{ $t('Не ответил(а)') }}</span>
                </li>
            </ul>
        </div>

        <div v-if="mine?.status" class="flex flex-wrap gap-2">
            <button v-if="mine.status !== 'safe'" class="btn-secondary" :disabled="busy" @click="respond('safe')">{{ $t('Теперь я в безопасности') }}</button>
            <button v-if="store.user.can_write && (check.creator?.id === store.user.id || store.user.is_admin)" class="btn-ghost" @click="close">
                <Icon name="x" /> {{ $t('Закрыть опрос') }}
            </button>
        </div>
    </section>

    <section v-else-if="loaded && store.user.can_write" class="card flex flex-wrap items-center gap-3">
        <span class="flex-1 font-semibold">{{ $t('После тревоги или взрывов узнайте, у всех ли всё хорошо.') }}</span>
        <button class="btn-primary" @click="asking = true"><Icon name="shield" /> {{ $t('Спросить: все ли в порядке?') }}</button>
    </section>

    <Modal v-if="asking" :title="$t('Спросить всех: все ли в порядке?')" @close="asking = false">
        <form class="space-y-4" @submit.prevent="ask">
            <p>{{ $t('Всем придёт срочное уведомление со звуком. Каждый ответит одной кнопкой.') }}</p>
            <div>
                <label class="field-label" for="ask-note">{{ $t('Комментарий (необязательно)') }}</label>
                <input id="ask-note" v-model="askNote" class="field" :placeholder="$t('Например: слышны взрывы в центре')">
            </div>
            <button class="btn-primary w-full text-lg" :disabled="busy"><Icon name="send" /> {{ $t('Спросить всех') }}</button>
        </form>
    </Modal>
</template>
