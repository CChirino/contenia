<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';

/**
 * Página mínima del wizard (la UI completa llega en el Paso 3 de la
 * implementación): muestra el track y el paso, con "Saltar por ahora".
 */
const props = defineProps<{
    track: string;
    step: number;
    brand?: Record<string, any> | null;
    plans?: { id: number; name: string; price_label: string; posts_per_month: number | null }[];
    state?: Record<string, any>;
    brands?: { id: number; name: string; slug: string }[] | null;
}>();

const skip = () => router.post('/onboarding/skip');
</script>

<template>
    <Head title="Primeros pasos" />
    <div class="flex min-h-screen flex-col items-center justify-center bg-surface-2 p-6 text-fg">
        <div class="w-full max-w-2xl rounded-lg bg-surface p-8 shadow">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-bold">Contenia<span class="text-brand-primary">.</span></h1>
                <button class="text-sm text-muted hover:text-fg" @click="skip">
                    Saltar por ahora
                </button>
            </div>
            <div class="mt-6">
                <p class="text-sm text-muted">
                    Recorrido <strong class="text-fg">{{ track }}</strong> · paso
                    {{ step }}{{ brand ? ` · marca: ${brand.name}` : '' }}
                </p>
                <p class="mt-4 text-sm text-fg-2">
                    Asistente de primeros pasos.
                </p>
            </div>
        </div>
    </div>
</template>
