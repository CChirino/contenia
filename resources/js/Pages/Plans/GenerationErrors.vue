<script setup lang="ts">
import { GenerationError } from '@/types';
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useBrandRoute } from '@/composables/useBrandRoute';

const route = useBrandRoute();

const props = defineProps<{
    year: number;
    month: number;
    errors: GenerationError[];
}>();

const formatDay = (iso: string) => {
    const [, m, d] = iso.split('-');
    return `${d}/${m}`;
};

const truncate = (text: string) =>
    text.length > 200 ? text.slice(0, 200) + '…' : text;

const weeksLabel = computed(() =>
    props.errors.length === 1 ? '1 semana' : `${props.errors.length} semanas`
);

const dismiss = () => {
    router.delete(route('plans.error.clear', { year: props.year, month: props.month }), {
        preserveScroll: true,
        onSuccess: () => router.reload({ only: ['plan'] }),
    });
};
</script>

<template>
    <div class="mb-4 rounded-md border border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/30 p-4 text-sm text-red-700 dark:text-red-300">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="font-semibold">
                    La generación tuvo problemas en {{ weeksLabel }}
                </p>
                <ul class="mt-2 list-inside list-disc space-y-1">
                    <li v-for="(error, i) in errors" :key="i" :title="error.message">
                        {{ formatDay(error.from) }} – {{ formatDay(error.to) }}:
                        {{ truncate(error.message) }}
                    </li>
                </ul>
                <p class="mt-2 text-xs text-red-600 dark:text-red-400">
                    Las semanas restantes se generaron correctamente. Puedes volver a
                    intentar con 'Regenerar mes' (regenera solo los posts en borrador).
                </p>
            </div>
            <button
                type="button"
                class="shrink-0 rounded-md border border-red-300 dark:border-red-700 px-3 py-1 text-xs font-medium text-red-700 dark:text-red-300 hover:bg-red-100 dark:hover:bg-red-900/40"
                @click="dismiss"
            >
                Descartar
            </button>
        </div>
    </div>
</template>
