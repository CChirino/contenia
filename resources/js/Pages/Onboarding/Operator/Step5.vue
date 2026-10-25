<script setup lang="ts">
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';

// Pautas + cierre: "Empezar" fija onboarded_at y lleva a la grilla.
const props = defineProps<{ brands?: { id: number; name: string; slug: string }[] }>();

const guidelinesUrl = computed(() => {
    const slug = props.brands?.[0]?.slug;
    return slug ? `/b/${slug}/settings/guidelines?from=onboarding` : null;
});

const start = () => router.post('/onboarding/complete');
</script>

<template>
    <div>
        <h2 class="text-2xl font-bold text-fg">Pautas</h2>
        <p class="mt-2 text-fg-2">
            La IA propone pautas a partir de tus ediciones y del feedback del cliente;
            tú las activas — y entran en la siguiente generación.
        </p>

        <div class="mt-6 rounded-lg bg-surface p-6 shadow-sm ring-1 ring-line">
            <svg class="h-16 w-16 text-brand-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 6h16 M4 12h16 M4 18h10" />
            </svg>
            <ul class="mt-3 space-y-1 text-sm text-muted">
                <li>· Las pendientes esperan tu «Activar» o «Descartar».</li>
                <li>· La IA también propone retirar pautas obsoletas.</li>
                <li>· Todo lo activo se inyecta en el prompt de generación.</li>
            </ul>
        </div>

        <div class="mt-6 flex items-center gap-4">
            <PrimaryButton class="px-6 py-3" @click="start">Empezar →</PrimaryButton>
            <a
                v-if="guidelinesUrl"
                :href="guidelinesUrl"
                class="inline-flex items-center rounded-md border border-line-strong bg-surface px-4 py-2 text-xs font-semibold uppercase tracking-widest text-fg-2 shadow-sm transition duration-150 ease-in-out hover:bg-surface-2"
            >
                Ir a verlo →
            </a>
        </div>
    </div>
</template>
