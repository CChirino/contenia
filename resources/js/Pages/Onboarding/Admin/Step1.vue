<script setup lang="ts">
// Bienvenida: qué es Contenia en tres tarjetas.
const cards = [
    {
        title: 'Marcas',
        text: 'Cada cliente tiene su perfil, paleta, pautas y equipo.',
        icon: 'M4 6h16v12H4z M4 10h16 M8 6V4h8v2',
    },
    {
        title: 'Grilla con IA',
        text: 'La IA planifica y redacta los posts del mes por red.',
        icon: 'M8 3v4 M16 3v4 M4 9h16 M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1z M8 14l2 2 4-4',
    },
    {
        title: 'Aprobación del cliente',
        text: 'Tu cliente revisa y comenta desde un enlace, sin cuenta.',
        icon: 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z',
    },
];
</script>

<template>
    <div>
        <h2 class="text-2xl font-bold text-fg">Bienvenido a Contenia</h2>
        <p class="mt-2 text-fg-2">
            Vamos a configurar tu primera marca y, en ~5 minutos, tendrás tu
            primera grilla de contenido generada con IA.
        </p>

        <div class="mt-8 grid gap-4 sm:grid-cols-3">
            <div
                v-for="card in cards"
                :key="card.title"
                class="rounded-lg bg-surface p-5 shadow-sm ring-1 ring-line"
            >
                <svg
                    class="h-8 w-8 text-brand-primary"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path :d="card.icon" />
                </svg>
                <h3 class="mt-3 font-semibold text-fg">{{ card.title }}</h3>
                <p class="mt-1 text-sm text-muted">{{ card.text }}</p>
            </div>
        </div>
    </div>
</template>
