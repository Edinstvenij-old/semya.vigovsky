<script setup>
import { computed } from 'vue';

const props = defineProps({ user: Object, size: { type: String, default: 'md' } });
const initials = computed(() =>
    (props.user?.name || '?').split(/\s+/).map((p) => p[0]).slice(0, 2).join('').toUpperCase(),
);
const sizes = { sm: 'h-8 w-8 text-sm', md: 'h-11 w-11 text-base', lg: 'h-16 w-16 text-xl' };
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center justify-center rounded-full font-bold text-white"
        :class="sizes[size]"
        :style="{ backgroundColor: user?.avatar_color || '#475569' }"
        :title="user?.name"
        aria-hidden="true"
    >{{ initials }}</span>
</template>
