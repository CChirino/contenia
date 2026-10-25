<script setup lang="ts">
import { computed } from 'vue';

// Bienvenida del operador: en qué marcas trabaja y qué verá.
const props = defineProps<{ brands?: { id: number; name: string; slug: string }[] }>();

const names = computed(() => (props.brands ?? []).map((b) => b.name));
</script>

<template>
    <div>
        <h2 class="text-2xl font-bold text-fg">Bienvenido a Contenia</h2>

        <p v-if="names.length" class="mt-2 text-fg-2">
            Trabajas en <strong class="text-fg">{{ names.join(' y ') }}</strong>.
            En esta guía de 5 pantallas verás cómo se planifica, aprueba y publica el contenido.
        </p>
        <p v-else class="mt-2 text-fg-2">
            Todavía no tienes marcas asignadas — pide al administrador que te asigne una.
            Mientras tanto puedes saltar la guía.
        </p>

        <div class="mt-8 rounded-lg bg-surface p-6 shadow-sm ring-1 ring-line">
            <svg class="h-16 w-16 text-brand-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="17" rx="2" />
                <path d="M3 9h18 M8 2v4 M16 2v4 M8 14l2 2 4-4" />
            </svg>
            <p class="mt-3 text-sm text-muted">
                En ~3 minutos sabrás moverte por la grilla, el drawer del post, la
                revisión del cliente y las pautas aprendidas por la IA.
            </p>
        </div>
    </div>
</template>
