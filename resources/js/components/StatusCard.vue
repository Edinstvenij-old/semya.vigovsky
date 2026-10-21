<script setup>
import { ago } from '../format';
import Avatar from './Avatar.vue';
import Icon from './Icon.vue';

defineProps({ checkin: Object });
</script>

<template>
    <div class="flex items-center gap-3">
        <Avatar :user="checkin.user" />
        <div class="min-w-0 flex-1">
            <div class="font-bold">{{ checkin.user?.name }}</div>
            <div class="flex items-center gap-1.5 text-lg" :class="checkin.type === 'need_help' ? 'font-bold text-red-700' : ''">
                <Icon :name="checkin.icon" /> <span class="truncate">{{ checkin.label }}</span>
            </div>
            <div class="text-sm text-slate-600">
                {{ ago(checkin.created_at) }}
                <template v-if="checkin.trip"> · {{ checkin.trip.title }}</template>
                <template v-if="checkin.note && checkin.type !== 'custom'"> · {{ checkin.note }}</template>
            </div>
        </div>
        <a v-if="checkin.lat" :href="`https://www.openstreetmap.org/?mlat=${checkin.lat}&mlon=${checkin.lng}#map=13/${checkin.lat}/${checkin.lng}`"
           target="_blank" rel="noopener noreferrer" class="btn-ghost px-3" aria-label="Показать на карте">
            <Icon name="map-pin" />
        </a>
    </div>
</template>
