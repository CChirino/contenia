<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface UserRow {
    id: number;
    name: string;
    email: string;
    role: string;
    brands: { id: number; name: string }[];
    deleted_at: string | null;
    is_self: boolean;
}

const page = usePage();
const deleteError = computed(() => (page.props.errors as Record<string, string>)?.user);

const destroyUser = (user: UserRow) => {
    if (!confirm(`¿Eliminar a ${user.name}? No podrá iniciar sesión. Podrás restaurarlo después.`)) return;
    router.delete(route('users.destroy', { user: user.id }), { preserveScroll: true });
};

const restoreUser = (user: UserRow) =>
    router.post(route('users.restore', { user: user.id }), {}, { preserveScroll: true });

const formatDate = (iso: string | null) =>
    iso ? new Date(iso).toLocaleDateString('es-VE', { day: '2-digit', month: 'short', year: 'numeric' }) : '';

const roleLabels: Record<string, string> = {
    admin: 'Admin',
    gestor: 'Gestor',
    operator: 'Operador',
};

const roleBadgeClass = (role: string) =>
    role === 'admin'
        ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-500/30 dark:text-indigo-200'
        : role === 'gestor'
          ? 'bg-teal-100 text-teal-800 dark:bg-teal-500/30 dark:text-teal-200'
          : 'bg-surface-3 text-fg-2';

defineProps<{
    users: UserRow[];
    deletedUsers: UserRow[];
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
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <InputError :message="deleteError" class="px-4 sm:px-0" />
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
                                        :class="roleBadgeClass(user.role)"
                                    >
                                        {{ roleLabels[user.role] ?? user.role }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-fg-2">
                                    {{ user.role === 'admin' ? 'Todas' : user.brands.map((b) => b.name).join(', ') || '—' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                    <Link :href="route('users.edit', { user: user.id })" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-200">
                                        Editar
                                    </Link>
                                    <button
                                        v-if="!user.is_self"
                                        type="button"
                                        class="ms-4 text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                                        @click="destroyUser(user)"
                                    >
                                        Eliminar
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="deletedUsers.length" class="overflow-x-auto bg-surface shadow-sm sm:rounded-lg">
                    <h3 class="px-6 pt-5 text-sm font-semibold text-fg">Usuarios eliminados</h3>
                    <p class="px-6 pt-1 text-xs text-muted">No pueden iniciar sesión. Al restaurarlos recuperan su rol y sus marcas.</p>
                    <table class="mt-3 min-w-full divide-y divide-line">
                        <tbody class="divide-y divide-line">
                            <tr v-for="user in deletedUsers" :key="user.id" class="text-muted">
                                <td class="px-6 py-3 font-medium">{{ user.name }}</td>
                                <td class="px-6 py-3 text-sm">{{ user.email }}</td>
                                <td class="px-6 py-3 text-sm">{{ roleLabels[user.role] ?? user.role }}</td>
                                <td class="px-6 py-3 text-sm">Eliminado el {{ formatDate(user.deleted_at) }}</td>
                                <td class="px-6 py-3 text-right text-sm">
                                    <button
                                        type="button"
                                        class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-200"
                                        @click="restoreUser(user)"
                                    >
                                        Restaurar
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
