<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    users: {
        id: number;
        name: string;
        email: string;
        role: string;
        brands: { id: number; name: string }[];
    }[];
}>();
</script>

<template>
    <Head title="Administración · Usuarios" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-fg">
                    Administración · Usuarios
                </h2>
                <Link :href="route('users.create')">
                    <PrimaryButton>Nuevo operador</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-x-auto bg-surface shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-line">
                        <thead class="bg-surface-2">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted">Nombre</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted">Rol</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted">Marcas</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line bg-surface">
                            <tr v-for="user in users" :key="user.id">
                                <td class="px-6 py-4 font-medium text-fg">{{ user.name }}</td>
                                <td class="px-6 py-4 text-sm text-fg-2">{{ user.email }}</td>
                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex rounded-full px-2 text-xs font-semibold leading-5"
                                        :class="user.role === 'admin' ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-500/30 dark:text-indigo-200' : 'bg-surface-3 text-fg-2'"
                                    >
                                        {{ user.role === 'admin' ? 'Admin' : 'Operador' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-fg-2">
                                    {{ user.role === 'admin' ? 'Todas' : user.brands.map((b) => b.name).join(', ') || '—' }}
                                </td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <Link :href="route('users.edit', { user: user.id })" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-200">
                                        Editar
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
