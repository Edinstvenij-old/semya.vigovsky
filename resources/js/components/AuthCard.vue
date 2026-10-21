<script setup>
import { LOCALES, getLocale, setLocale } from '../i18n';

defineProps({ title: String, subtitle: String });
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-brand-900 p-4">
        <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl sm:p-8">
            <div class="mb-6 flex flex-col items-center gap-2 text-center">
                <img :src="'/crest.png'" :alt="$t('Семейный герб')" class="h-28 w-auto">
                <h1 class="text-2xl font-bold break-words">{{ title }}</h1>
                <p v-if="subtitle" class="text-slate-600">{{ subtitle }}</p>
            </div>
            <slot />
            <div class="mt-6 flex justify-center gap-2" role="group" aria-label="Мова / Язык">
                <button v-for="(label, code) in LOCALES" :key="code" type="button" class="btn-ghost min-h-11 text-sm"
                        :class="getLocale() === code ? 'underline' : ''" :aria-pressed="getLocale() === code" @click="setLocale(code)">{{ label }}</button>
            </div>
        </div>
    </div>
</template>
