<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    editUser: {
        id: number;
        name: string;
        email: string;
        role: string;
        brand_ids: number[];
    } | null;
    brands: { id: number; name: string }[];
    roles: string[];
}>();

const form = useForm({
    name: props.editUser?.name ?? '',
    email: props.editUser?.email ?? '',
    password: '',
    role: props.editUser?.role ?? 'operator',
    brand_ids: [...(props.editUser?.brand_ids ?? [])],
});

const submit = () => {
    if (props.editUser) {
        form.put(route('users.update', { user: props.editUser.id }));
    } else {
        form.post(route('users.store'));
    }
};
</script>

<template>
    <Head :title="editUser ? 'Editar usuario' : 'Nuevo operador'" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Administración · {{ editUser ? `Editar ${editUser.name}` : 'Nuevo operador' }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <form
                    class="space-y-6 overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg"
                    @submit.prevent="submit"
                >
                    <div>
                        <InputLabel for="name" value="Nombre" />
                        <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required />
                        <InputError :message="form.errors.name" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="email" value="Email" />
                        <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required />
                        <InputError :message="form.errors.email" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel
                            for="password"
                            :value="editUser ? 'Contraseña (dejar vacío para no cambiar)' : 'Contraseña temporal'"
                        />
                        <TextInput
                            id="password"
                            v-model="form.password"
                            type="password"
                            class="mt-1 block w-full"
                            :required="!editUser"
                        />
                        <InputError :message="form.errors.password" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="role" value="Rol" />
                        <select
                            id="role"
                            v-model="form.role"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option v-for="role in roles" :key="role" :value="role">
                                {{ role === 'admin' ? 'Admin' : 'Operador' }}
                            </option>
                        </select>
                        <InputError :message="form.errors.role" class="mt-2" />
                    </div>

                    <fieldset>
                        <legend class="text-sm font-medium text-gray-700">Marcas asignadas</legend>
                        <div class="mt-2 space-y-2">
                            <label v-for="brand in brands" :key="brand.id" class="flex items-center gap-2">
                                <Checkbox v-model:checked="form.brand_ids" :value="brand.id" />
                                <span class="text-sm text-gray-700">{{ brand.name }}</span>
                            </label>
                        </div>
                        <InputError :message="form.errors.brand_ids" class="mt-2" />
                    </fieldset>

                    <div class="flex items-center justify-end gap-4">
                        <Link :href="route('users.index')" class="text-sm text-gray-600 hover:text-gray-900">
                            Cancelar
                        </Link>
                        <PrimaryButton :disabled="form.processing">
                            {{ editUser ? 'Guardar' : 'Crear operador' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
