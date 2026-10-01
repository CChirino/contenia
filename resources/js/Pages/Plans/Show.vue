<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    year: number;
    month: number;
}>();
</script>

<template>
    <Head title="Plan mensual" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Plan {{ month }}/{{ year }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <h1>Plan</h1>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
