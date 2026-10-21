<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import AppLayout from './components/AppLayout.vue';

const route = useRoute();
const bare = computed(() => route.meta.guest || route.meta.bare);
</script>

<template>
    <RouterView v-if="bare" />
    <AppLayout v-else>
        <RouterView :key="route.path" />
    </AppLayout>
</template>
