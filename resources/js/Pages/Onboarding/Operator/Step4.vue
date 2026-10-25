<script setup lang="ts">
import { computed } from 'vue';

// Aprobación del cliente: enviar a aprobar, qué ve el cliente, rechazos.
const props = defineProps<{ brands?: { id: number; name: string; slug: string }[] }>();

const gridUrl = computed(() => {
    const slug = props.brands?.[0]?.slug;
    if (!slug) return null;
    const d = new Date();
    return `/b/${slug}/plans/${d.getFullYear()}/${d.getMonth() + 1}?from=onboarding`;
});
</script>

<template>
    <div>
        <h2 class="text-2xl font-bold text-fg">Aprobación del cliente</h2>
        <p class="mt-2 text-fg-2">
            Con «Enviar a aprobación» generas un enlace: el cliente aprueba o
            rechaza cada post sin cuenta. Los rechazos vuelven a borrador con su comentario.
        </p>

        <div class="mt-6 rounded-lg bg-surface p-6 shadow-sm ring-1 ring-line">
            <svg class="h-16 w-16 text-brand-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 6h16v12H4z M4 7l8 6 8-6" />
            </svg>
            <ul class="mt-3 space-y-1 text-sm text-muted">
                <li>· El enlace caduca en 14 días y muestra solo esa revisión.</li>
                <li>· Un rechazo exige comentario — ese comentario alimenta las pautas.</li>
                <li>· El cliente ve los diseños tal cual se publicarán.</li>
            </ul>
        </div>

        <div class="mt-4" v-if="gridUrl">
            <a
                :href="gridUrl"
                class="inline-flex items-center rounded-md border border-line-strong bg-surface px-4 py-2 text-xs font-semibold uppercase tracking-widest text-fg-2 shadow-sm transition duration-150 ease-in-out hover:bg-surface-2"
            >
                Ir a verlo →
            </a>
        </div>
    </div>
</template>
