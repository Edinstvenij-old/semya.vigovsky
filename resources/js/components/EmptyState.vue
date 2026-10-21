<script setup>
import Icon from './Icon.vue';

defineProps({ icon: { type: String, default: 'folder' }, text: String });
</script>

<template>
    <div class="flex flex-col items-center gap-2 rounded-2xl border-2 border-dashed border-slate-300 p-6 text-center text-slate-600">
        <Icon :name="icon" size="2em" />
        <p class="font-semibold">{{ text }}</p>
        <slot />
    </div>
</template>
