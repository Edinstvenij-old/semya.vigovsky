<script setup>
import { onMounted, reactive, ref } from 'vue';
import { api, errorText, fieldErrors } from '../api';
import { PRIORITY_LABELS } from '../format';
import { loadMembers, store } from '../store';
import Modal from './Modal.vue';

const props = defineProps({ task: Object, categories: Array, defaultCategory: [Number, String] });
const emit = defineEmits(['close', 'saved']);

const form = reactive({
    title: props.task?.title || '',
    description: props.task?.description || '',
    category_id: props.task?.category_id ?? props.defaultCategory ?? '',
    assignee_id: props.task?.assignee_id ?? '',
    due_date: props.task?.due_date || '',
    priority: props.task?.priority || 'normal',
    checklistText: '',
});
const errors = ref({});
const error = ref('');
const busy = ref(false);

onMounted(() => loadMembers());

async function save() {
    busy.value = true;
    errors.value = {};
    error.value = '';
    const payload = {
        title: form.title,
        description: form.description || null,
        category_id: form.category_id || null,
        assignee_id: form.assignee_id || null,
        due_date: form.due_date || null,
        priority: form.priority,
    };
    try {
        const { data } = props.task
            ? await api.patch(`/tasks/${props.task.id}`, payload)
            : await api.post('/tasks', { ...payload, checklist: form.checklistText.split('\n').filter((l) => l.trim()) });
        emit('saved', data);
    } catch (e) {
        errors.value = fieldErrors(e);
        error.value = errorText(e);
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Modal :title="task ? $t('Изменить задачу') : $t('Новая задача')" @close="emit('close')">
        <form class="space-y-4" @submit.prevent="save">
            <div>
                <label class="field-label" for="t-title">{{ $t('Что нужно сделать?') }}</label>
                <input id="t-title" v-model="form.title" class="field" required maxlength="200" :placeholder="$t('Например: заменить аккумулятор в ИБП')">
                <p v-if="errors.title" class="field-error">{{ errors.title }}</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="field-label" for="t-cat">{{ $t('Категория') }}</label>
                    <select id="t-cat" v-model="form.category_id" class="field">
                        <option value="">{{ $t('Без категории') }}</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="field-label" for="t-who">{{ $t('Кто делает') }}</label>
                    <select id="t-who" v-model="form.assignee_id" class="field">
                        <option value="">{{ $t('Не назначено') }}</option>
                        <option v-for="m in store.members.filter((m) => m.role !== 'viewer')" :key="m.id" :value="m.id">{{ m.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="field-label" for="t-due">{{ $t('Срок') }}</label>
                    <input id="t-due" v-model="form.due_date" type="date" class="field">
                </div>
                <div>
                    <label class="field-label" for="t-prio">{{ $t('Важность') }}</label>
                    <select id="t-prio" v-model="form.priority" class="field">
                        <option v-for="(label, key) in PRIORITY_LABELS" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="field-label" for="t-desc">{{ $t('Описание') }}</label>
                <textarea id="t-desc" v-model="form.description" class="field" rows="3" />
            </div>
            <div v-if="!task">
                <label class="field-label" for="t-check">{{ $t('Чек-лист — каждый пункт с новой строки') }}</label>
                <textarea id="t-check" v-model="form.checklistText" class="field" rows="4"
                          :placeholder="$t('Кабель ВВГнг-LS 3×2.5 — 20 м\nАвтомат C16\nАккумулятор 12V 9Ah AGM')" />
            </div>
            <p v-if="error" class="field-error" role="alert">{{ error }}</p>
            <button class="btn-primary w-full text-lg" :disabled="busy">{{ task ? $t('Сохранить') : $t('Создать задачу') }}</button>
        </form>
    </Modal>
</template>
