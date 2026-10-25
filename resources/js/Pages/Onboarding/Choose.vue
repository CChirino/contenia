<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';

defineProps<{ state?: Record<string, any> }>();

const choose = (track: 'admin' | 'operator') =>
    router.patch('/onboarding/state', { track });
</script>

<template>
    <Head title="¿Por dónde empezamos?" />
    <div class="flex min-h-screen flex-col items-center justify-center bg-surface-2 p-6 text-fg">
        <span class="text-2xl font-bold">Contenia<span class="text-brand-primary">.</span></span>
        <h1 class="mt-6 text-xl font-semibold">¿Por dónde empezamos?</h1>

        <div class="mt-8 grid w-full max-w-2xl gap-4 sm:grid-cols-2">
            <button
                type="button"
                class="rounded-lg bg-surface p-6 text-left shadow ring-1 ring-line transition hover:ring-2 hover:ring-brand-primary"
                @click="choose('admin')"
            >
                <svg class="h-10 w-10 text-brand-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 3l1.8 4.6L18 9l-4.2 1.4L12 15l-1.8-4.6L6 9l4.2-1.4z" />
                    <path d="M19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9z" />
                </svg>
                <p class="mt-4 font-semibold">Crear una marca nueva con IA</p>
                <p class="mt-1 text-sm text-muted">
                    Configura otra marca en ~5 minutos: la IA propone todo y solo revisas.
                </p>
            </button>

            <button
                type="button"
                class="rounded-lg bg-surface p-6 text-left shadow ring-1 ring-line transition hover:ring-2 hover:ring-brand-primary"
                @click="choose('operator')"
            >
                <svg class="h-10 w-10 text-brand-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="17" rx="2" />
                    <path d="M3 9h18 M8 2v4 M16 2v4" />
                    <circle cx="12" cy="14" r="2.4" />
                </svg>
                <p class="mt-4 font-semibold">Ver cómo funciona</p>
                <p class="mt-1 text-sm text-muted">
                    Un recorrido guiado por la grilla, el post, la revisión del cliente y las pautas.
                </p>
            </button>
        </div>

        <button
            type="button"
            class="mt-8 text-sm text-muted underline hover:text-fg"
            @click="router.post('/onboarding/skip')"
        >
            Saltar por ahora
        </button>
    </div>
</template>
