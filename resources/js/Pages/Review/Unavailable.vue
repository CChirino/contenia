<script setup lang="ts">
import ReviewLayout from '@/Layouts/ReviewLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{ reason: 'revoked' | 'expired' | 'not_found' }>();

const messages: Record<string, { title: string; body: string }> = {
    revoked: {
        title: 'Enlace revocado',
        body: 'Este enlace de revisión fue revocado. Pide al equipo un nuevo enlace.',
    },
    expired: {
        title: 'Enlace vencido',
        body: 'Este enlace de revisión ya venció. Pide al equipo que genere uno nuevo.',
    },
    not_found: {
        title: 'Enlace no válido',
        body: 'No encontramos esta revisión. Verifica el enlace o pide uno nuevo.',
    },
};

const message = computed(() => messages[props.reason] ?? messages.not_found);
</script>

<template>
    <Head title="Revisión no disponible" />
    <ReviewLayout>
        <div class="flex min-h-screen items-center justify-center p-6">
            <div class="w-full max-w-md rounded-xl bg-white p-8 text-center shadow-sm">
                <h1 class="text-xl font-semibold text-gray-900">{{ message.title }}</h1>
                <p class="mt-2 text-sm text-gray-600">{{ message.body }}</p>
            </div>
        </div>
    </ReviewLayout>
</template>
