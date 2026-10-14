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
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Administración · Usuarios
                </h2>
                <Link :href="route('users.create')">
                    <PrimaryButton>Nuevo operador</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Nombre</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Rol</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Marcas</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="user in users" :key="user.id">
                                <td class="px-6 py-4 font-medium text-gray-900">{{ user.name }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ user.email }}</td>
                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex rounded-full px-2 text-xs font-semibold leading-5"
                                        :class="user.role === 'admin' ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-600'"
                                    >
                                        {{ user.role === 'admin' ? 'Admin' : 'Operador' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    {{ user.role === 'admin' ? 'Todas' : user.brands.map((b) => b.name).join(', ') || '—' }}
                                </td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <Link :href="route('users.edit', { user: user.id })" class="text-indigo-600 hover:text-indigo-900">
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
