<script setup>
import { computed, onMounted, ref } from 'vue';
import { api } from '../api';
import EmptyState from '../components/EmptyState.vue';
import { monthYear } from '../format';
import PhotoViewer from '../components/PhotoViewer.vue';

const photos = ref([]);
const cursor = ref(null);
const loading = ref(false);
const loaded = ref(false);
const viewerIndex = ref(null);

async function loadMore() {
    loading.value = true;
    try {
        const { data } = await api.get('/photos', { params: { cursor: cursor.value || undefined } });
        photos.value.push(...data.photos);
        cursor.value = data.next_cursor;
    } finally {
        loading.value = false;
        loaded.value = true;
    }
}

onMounted(loadMore);

/** Photos grouped by month, newest first: "октябрь 2026". */
const months = computed(() => {
    const groups = [];
    photos.value.forEach((p, index) => {
        const label = monthYear(p.created_at);
        if (groups.at(-1)?.label !== label) groups.push({ label, items: [] });
        groups.at(-1).items.push({ ...p, index });
    });
    return groups;
});
</script>

<template>
    <div class="space-y-4">
        <h1 class="page-title">Семейный фотоальбом</h1>
        <p class="text-slate-600">Все фото из задач и чатов в одном месте. Билеты и документы сюда не попадают.</p>

        <section v-for="m in months" :key="m.label">
            <h2 class="mb-2 text-lg font-bold">{{ m.label }}</h2>
            <div class="grid grid-cols-3 gap-1 sm:grid-cols-4 lg:grid-cols-6">
                <button v-for="p in m.items" :key="p.id" class="aspect-square overflow-hidden rounded-lg bg-slate-200"
                        :aria-label="`Открыть фото: ${p.source?.label || p.name}`" @click="viewerIndex = p.index">
                    <img :src="p.thumb_url" :alt="p.name" class="h-full w-full object-cover" loading="lazy">
                </button>
            </div>
        </section>

        <EmptyState v-if="loaded && !photos.length" icon="image" text="Фото пока нет. Добавляйте их в задачи или отправляйте в чат." />
        <button v-if="cursor" class="btn-secondary w-full" :disabled="loading" @click="loadMore">{{ loading ? 'Загрузка…' : 'Показать ещё' }}</button>

        <PhotoViewer v-if="viewerIndex !== null" :photos="photos" :start="viewerIndex" @close="viewerIndex = null" />
    </div>
</template>
