<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';

defineProps<{ state?: Record<string, any> }>();

const choose = (track: 'admin' | 'operator') =>
    router.patch('/onboarding/state', { track });
</script>

<template>
    <Head title="¿Por dónde empezamos?" />
    <div class="flex min-h-screen flex-col items-center justify-center bg-surface-2 p-6 text-fg">
        <h1 class="text-2xl font-bold">¿Por dónde empezamos?</h1>
        <div class="mt-6 grid w-full max-w-2xl gap-4 sm:grid-cols-2">
            <button
                class="rounded-lg bg-surface p-6 text-left shadow hover:ring-2 hover:ring-brand-primary"
                @click="choose('admin')"
            >
                <p class="font-semibold">Crear una marca nueva con IA</p>
                <p class="mt-1 text-sm text-muted">Configura otra marca en ~5 minutos.</p>
            </button>
            <button
                class="rounded-lg bg-surface p-6 text-left shadow hover:ring-2 hover:ring-brand-primary"
                @click="choose('operator')"
            >
                <p class="font-semibold">Ver cómo funciona</p>
                <p class="mt-1 text-sm text-muted">Un recorrido por la grilla y las pautas.</p>
            </button>
        </div>
    </div>
</template>
