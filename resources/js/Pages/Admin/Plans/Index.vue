<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';

interface Plan {
    id: number;
    name: string;
    posts_per_month: number | null;
    regenerations_per_month: number | null;
    image_refreshes_per_month: number | null;
    networks: string[];
    price_label: string | null;
    brands_count: number;
}

defineProps<{ plans: Plan[] }>();

const networkLabels: Record<string, string> = {
    instagram: 'Instagram',
    linkedin: 'LinkedIn',
    tiktok: 'TikTok',
};

const limitLabel = (v: number | null) => (v === null ? '∞' : v);

const destroy = (plan: Plan) => {
    if (!confirm(`¿Eliminar el plan «${plan.name}»?`)) return;
    router.delete(route('admin.plans.destroy', { plan: plan.id }));
};
</script>

<template>
    <Head title="Administración · Planes" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Administración · Planes
                </h2>
                <Link :href="route('admin.plans.create')">
                    <PrimaryButton>Nuevo plan</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Plan</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Posts/mes</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Regen/mes</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Fondos/mes</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Redes</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Precio</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Marcas</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="plan in plans" :key="plan.id">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ plan.name }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ limitLabel(plan.posts_per_month) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ limitLabel(plan.regenerations_per_month) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ limitLabel(plan.image_refreshes_per_month) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    {{ plan.networks.map((n) => networkLabels[n] ?? n).join(', ') }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ plan.price_label ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ plan.brands_count }}</td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <Link
                                        :href="route('admin.plans.edit', { plan: plan.id })"
                                        class="text-indigo-600 hover:text-indigo-900"
                                    >
                                        Editar
                                    </Link>
                                    <button
                                        type="button"
                                        class="ms-4 text-red-600 hover:text-red-800 disabled:opacity-40"
                                        :disabled="plan.brands_count > 0"
                                        :title="plan.brands_count > 0 ? 'El plan tiene marcas asignadas' : 'Eliminar plan'"
                                        @click="destroy(plan)"
                                    >
                                        Eliminar
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
