<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import Icon from './Icon.vue';

defineProps({ title: String, wide: Boolean });
const emit = defineEmits(['close']);
const dialog = ref(null);

function onKey(e) {
    if (e.key === 'Escape') emit('close');
}

onMounted(async () => {
    document.addEventListener('keydown', onKey);
    document.body.style.overflow = 'hidden';
    await nextTick();
    dialog.value?.querySelector('input, textarea, select, button:not([data-close])')?.focus();
});
onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKey);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center sm:p-4" @click.self="emit('close')">
            <div
                ref="dialog"
                role="dialog"
                aria-modal="true"
                :aria-label="title"
                class="max-h-[92vh] w-full overflow-y-auto rounded-t-3xl bg-white p-5 shadow-xl sm:rounded-3xl"
                :class="wide ? 'sm:max-w-3xl' : 'sm:max-w-lg'"
            >
                <div class="mb-4 flex items-start justify-between gap-3">
                    <h2 class="text-xl font-bold">{{ title }}</h2>
                    <button data-close class="btn-ghost -mr-2 -mt-2 px-3" aria-label="Закрыть" @click="emit('close')">
                        <Icon name="x" />
                    </button>
                </div>
                <slot />
            </div>
        </div>
    </Teleport>
</template>
