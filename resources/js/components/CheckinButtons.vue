<script setup>
import { onMounted, ref } from 'vue';
import { api, errorText } from '../api';
import { toast } from '../store';
import Icon from './Icon.vue';
import Modal from './Modal.vue';
import { t } from '../i18n';

const props = defineProps({ trips: { type: Array, default: () => [] }, compact: Boolean });
const emit = defineEmits(['done']);

// The most used statuses first; the rest are behind "Другой статус".
const PRIMARY = ['safe', 'shelter', 'to_airport', 'border_crossed', 'arrived', 'back_home'];

const types = ref([]);
const showAll = ref(false);
const pending = ref(null);
const note = ref('');
const tripId = ref('');
const withLocation = ref(false);
const busy = ref(false);

onMounted(async () => {
    types.value = (await api.get('/checkin-types')).data;
});

function pick(type) {
    pending.value = type;
    note.value = '';
    showAll.value = false;
}

function position() {
    return new Promise((resolve) => {
        if (!withLocation.value || !navigator.geolocation) return resolve({});
        navigator.geolocation.getCurrentPosition(
            (p) => resolve({ lat: p.coords.latitude, lng: p.coords.longitude }),
            () => resolve({}),
            { timeout: 8000, maximumAge: 60000 },
        );
    });
}

async function send() {
    busy.value = true;
    try {
        const coords = await position();
        const { data } = await api.post('/checkins', {
            type: pending.value.value,
            note: note.value || null,
            trip_id: tripId.value || null,
            ...coords,
        });
        toast(data.queued ? t('Нет связи — статус отправится, когда появится интернет') : t('Статус отправлен семье'));
        pending.value = null;
        if (!data.queued) emit('done', data);
    } catch (e) {
        toast(errorText(e), 'error');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div>
        <div class="grid grid-cols-2 gap-3" :class="compact ? '' : 'lg:grid-cols-3'">
            <button
                v-for="t in types.filter((t) => PRIMARY.includes(t.value))"
                :key="t.value"
                class="flex min-h-20 flex-col items-center justify-center gap-1 rounded-2xl bg-brand-700 p-3 text-center text-lg font-bold text-white shadow hover:bg-brand-800"
                @click="pick(t)"
            >
                <Icon :name="t.icon" size="1.75em" />
                {{ t.label }}
            </button>
        </div>
        <div class="mt-3 grid grid-cols-2 gap-3">
            <button class="btn-secondary" @click="showAll = true"><Icon name="more" /> {{ $t('Другой статус') }}</button>
            <button class="btn-danger" @click="pick(types.find((t) => t.value === 'need_help'))"><Icon name="siren" /> {{ $t('Нужна помощь') }}</button>
        </div>

        <Modal v-if="showAll" :title="$t('Выберите статус')" @close="showAll = false">
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <button v-for="t in types" :key="t.value" class="btn-secondary justify-start" @click="pick(t)">
                    <Icon :name="t.icon" /> {{ t.label }}
                </button>
            </div>
        </Modal>

        <Modal v-if="pending" :title="pending.label" @close="pending = null">
            <form class="space-y-4" @submit.prevent="send">
                <div>
                    <label class="field-label" for="checkin-note">{{ pending.value === 'custom' ? $t('Ваш статус') : $t('Комментарий (необязательно)') }}</label>
                    <input id="checkin-note" v-model="note" class="field" :required="pending.value === 'custom'"
                           :placeholder="$t('Например: рейс задержали на час')">
                </div>
                <div v-if="props.trips.length">
                    <label class="field-label" for="checkin-trip">{{ $t('Поездка') }}</label>
                    <select id="checkin-trip" v-model="tripId" class="field">
                        <option value="">{{ $t('— без поездки —') }}</option>
                        <option v-for="trip in props.trips" :key="trip.id" :value="trip.id">{{ trip.title }}</option>
                    </select>
                </div>
                <label class="flex min-h-12 items-center gap-3 font-semibold">
                    <input v-model="withLocation" type="checkbox" class="h-6 w-6">
                    <Icon name="map-pin" /> {{ $t('Добавить моё местоположение') }}
                </label>
                <button class="btn-primary w-full text-lg" :disabled="busy"><Icon name="send" /> {{ $t('Отправить семье') }}</button>
            </form>
        </Modal>
    </div>
</template>
