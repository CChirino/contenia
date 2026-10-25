<script setup lang="ts">
import { computed } from 'vue';

// La grilla: estados de un post (mismos colores de dots que Plans/Show).
const props = defineProps<{ brands?: { id: number; name: string; slug: string }[] }>();

const dots: Record<string, string> = {
    drafted: 'bg-muted',
    approved: 'bg-blue-500',
    rendered: 'bg-teal-500',
    client_approved: 'bg-green-500',
    scheduled: 'bg-purple-500',
    published: 'bg-green-600',
    failed: 'bg-red-500',
};
const labels: Record<string, string> = {
    drafted: 'Borrador',
    approved: 'Aprobado',
    rendered: 'Renderizado',
    client_approved: 'Aprobado por el cliente',
    scheduled: 'Programado',
    published: 'Publicado',
    failed: 'Fallido',
};

const gridUrl = computed(() => {
    const slug = props.brands?.[0]?.slug;
    if (!slug) return null;
    const d = new Date();
    return `/b/${slug}/plans/${d.getFullYear()}/${d.getMonth() + 1}?from=onboarding`;
});
</script>

<template>
    <div>
        <h2 class="text-2xl font-bold text-fg">La grilla</h2>
        <p class="mt-2 text-fg-2">
            Es el calendario mensual de la marca: cada tarjeta es un post, con su
            estado en un punto de color. Desde ahí generas el mes completo o regeneras una semana.
        </p>

        <div class="mt-6 rounded-lg bg-surface p-6 shadow-sm ring-1 ring-line">
            <ul class="grid gap-3 text-sm sm:grid-cols-2">
                <li v-for="(cls, key) in dots" :key="key" class="flex items-center gap-2">
                    <span class="inline-block h-2.5 w-2.5 rounded-full" :class="cls" />
                    <span class="text-fg-2">{{ labels[key] }}</span>
                </li>
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
