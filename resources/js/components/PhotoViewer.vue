<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Icon from './Icon.vue';

/** Full-screen photo viewer: arrows / swipe / keyboard to move, Esc to close. */
const props = defineProps({
    photos: { type: Array, required: true }, // [{ url, original_name|name, source? }]
    start: { type: Number, default: 0 },
});
const emit = defineEmits(['close']);
const index = ref(props.start);
const photo = computed(() => props.photos[index.value]);
let touchX = null;

function go(delta) {
    index.value = (index.value + delta + props.photos.length) % props.photos.length;
}

function onKey(e) {
    if (e.key === 'Escape') emit('close');
    if (e.key === 'ArrowRight') go(1);
    if (e.key === 'ArrowLeft') go(-1);
}

function touchStart(e) {
    touchX = e.touches[0].clientX;
}

function touchEnd(e) {
    if (touchX === null) return;
    const dx = e.changedTouches[0].clientX - touchX;
    if (Math.abs(dx) > 50) go(dx < 0 ? 1 : -1);
    touchX = null;
}

onMounted(() => {
    document.addEventListener('keydown', onKey);
    document.body.style.overflow = 'hidden';
});
onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKey);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-50 flex flex-col bg-black text-white" role="dialog" aria-modal="true" :aria-label="$t('Просмотр фото')"
             @touchstart.passive="touchStart" @touchend.passive="touchEnd">
            <div class="flex items-center gap-2 p-2">
                <span class="min-w-0 flex-1 truncate px-2 text-sm opacity-80">{{ index + 1 }} / {{ photos.length }}<template v-if="photo.source?.label"> · {{ photo.source.label }}</template></span>
                <RouterLink v-if="photo.source?.url" :to="photo.source.url" class="btn shrink-0 text-white hover:bg-white/10" @click="emit('close')">
                    <Icon name="external-link" /> <span class="hidden sm:inline">{{ $t('Открыть') }}</span>
                </RouterLink>
                <a :href="photo.url" target="_blank" class="btn shrink-0 text-white hover:bg-white/10" :aria-label="$t('Скачать')"><Icon name="download" /></a>
                <button class="btn shrink-0 text-white hover:bg-white/10" :aria-label="$t('Закрыть')" @click="emit('close')"><Icon name="x" /></button>
            </div>
            <div class="relative flex min-h-0 flex-1 items-center justify-center">
                <img :src="photo.url" :alt="photo.original_name || photo.name" class="max-h-full max-w-full object-contain">
                <template v-if="photos.length > 1">
                    <button class="absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-black/50 p-3 hover:bg-black/70" :aria-label="$t('Предыдущее фото')" @click="go(-1)">
                        <Icon name="chevron-left" size="1.6em" />
                    </button>
                    <button class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-black/50 p-3 hover:bg-black/70" :aria-label="$t('Следующее фото')" @click="go(1)">
                        <Icon name="chevron-right" size="1.6em" />
                    </button>
                </template>
            </div>
        </div>
    </Teleport>
</template>
