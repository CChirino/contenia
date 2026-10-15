<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps<{
    brands: {
        id: number;
        name: string;
        slug: string;
        website_url: string | null;
        status: string;
        requires_client_approval: boolean;
        plan: { name: string; price_label: string | null } | null;
        operators_count: number;
        posts_this_month: number;
        logo_url: string;
    }[];
}>();

const toggle = (id: number) => {
    router.post(route('brands.toggle', { brand: id }));
};
</script>

<template>
    <Head title="Administración · Marcas" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-fg">
                    Administración · Marcas
                </h2>
                <Link :href="route('brands.create')">
                    <PrimaryButton>Nueva marca</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-surface shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-line">
                        <thead class="bg-surface-2">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted">Marca</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted">Plan</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted">Estado</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted">Operadores</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted">Posts del mes</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line bg-surface">
                            <tr v-for="brand in brands" :key="brand.id">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <img :src="brand.logo_url" :alt="brand.name" class="h-8 w-8 object-contain" />
                                        <div>
                                            <div class="font-medium text-fg">{{ brand.name }}</div>
                                            <div class="text-sm text-muted">{{ brand.slug }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-fg-2">{{ brand.plan?.name ?? '—' }}</td>
                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex rounded-full px-2 text-xs font-semibold leading-5"
                                        :class="brand.status === 'active' ? 'bg-green-100 text-green-800 dark:text-green-200 dark:bg-green-900/40' : 'bg-surface-3 text-fg-2'"
                                    >
                                        {{ brand.status === 'active' ? 'Activa' : 'Pausada' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-fg-2">{{ brand.operators_count }}</td>
                                <td class="px-6 py-4 text-sm text-fg-2">{{ brand.posts_this_month }}</td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <Link :href="route('brands.edit', { brand: brand.id })" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-200">
                                        Editar
                                    </Link>
                                    <button
                                        type="button"
                                        class="ms-4 text-sm text-fg-2 hover:text-fg"
                                        @click="toggle(brand.id)"
                                    >
                                        {{ brand.status === 'active' ? 'Pausar' : 'Activar' }}
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
