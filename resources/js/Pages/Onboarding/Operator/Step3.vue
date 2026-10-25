<script setup lang="ts">
import { computed } from 'vue';

// El post: el drawer y sus acciones (aprobar, editar, regenerar, diseños).
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
        <h2 class="text-2xl font-bold text-fg">El post</h2>
        <p class="mt-2 text-fg-2">
            Al pulsar una tarjeta se abre el drawer: ahí apruebas, editas el copy,
            regeneras con un comentario para la IA o generas su diseño.
        </p>

        <div class="mt-6 rounded-lg bg-surface p-6 shadow-sm ring-1 ring-line">
            <svg class="h-16 w-16 text-brand-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                <rect x="4" y="3" width="16" height="18" rx="2" />
                <path d="M8 8h8 M8 12h8 M8 16h5" />
            </svg>
            <ul class="mt-3 space-y-1 text-sm text-muted">
                <li>· <strong class="text-fg-2">Aprobar</strong>: pasa el post a aprobado.</li>
                <li>· <strong class="text-fg-2">Editar</strong>: cambias copy, hashtags y hora — queda en la memoria de la IA.</li>
                <li>· <strong class="text-fg-2">Regenerar</strong>: la IA reescribe el post con tu comentario (cuenta en la cuota).</li>
                <li>· <strong class="text-fg-2">Generar diseño / nuevo fondo</strong>: crea la imagen o video (también cuenta).</li>
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
