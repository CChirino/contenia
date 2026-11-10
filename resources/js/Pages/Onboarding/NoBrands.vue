<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';

// Operador sin marcas asignadas al cerrar el asistente: no hay grilla a
// la que llevarlo, así que se le pide que hable con su administrador.
const logout = () => router.post('/logout');
</script>

<template>
    <Head title="Sin marca asignada" />
    <div class="flex min-h-screen flex-col items-center justify-center bg-surface-2 p-6 text-fg">
        <span class="text-2xl font-bold">Contenia<span class="text-brand-primary">.</span></span>

        <div class="mt-8 w-full max-w-md rounded-lg bg-surface p-8 text-center shadow ring-1 ring-line">
            <svg
                class="mx-auto h-12 w-12 text-brand-primary"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.6"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <rect x="3" y="4" width="18" height="17" rx="2" />
                <path d="M3 9h18 M8 2v4 M16 2v4" />
                <circle cx="12" cy="14" r="2.4" />
            </svg>
            <h1 class="mt-4 text-xl font-semibold">Aún no tienes marca asignada</h1>
            <p class="mt-2 text-sm text-muted">
                Pide a tu administrador que te asigne una marca para empezar a trabajar en su grilla.
            </p>

            <PrimaryButton class="mt-6" @click="logout">
                Cerrar sesión
            </PrimaryButton>
        </div>
    </div>
</template>
