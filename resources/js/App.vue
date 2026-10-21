<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import AppLayout from './components/AppLayout.vue';
import { store } from './store';

const route = useRoute();
const bare = computed(() => route.meta.guest || route.meta.bare);
</script>

<template>
    <RouterView v-if="bare" />
    <!-- Signed-in pages only render with a user: if the session expires mid-page, they
         unmount at once (instead of crashing on store.user) while the router goes to /login. -->
    <AppLayout v-else-if="store.user">
        <RouterView :key="route.path" />
    </AppLayout>
</template>
