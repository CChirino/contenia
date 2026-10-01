<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { ContentPillar } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    pillars: ContentPillar[];
}>();

const totalWeight = computed(() =>
    props.pillars.reduce((sum, p) => sum + p.weight, 0)
);

const editingId = ref<number | null>(null);

const createForm = useForm({
    name: '',
    description: '',
    weight: '0',
});

const editForm = useForm({
    name: '',
    description: '',
    weight: '0',
});

const startEdit = (pillar: ContentPillar) => {
    editingId.value = pillar.id;
    editForm.name = pillar.name;
    editForm.description = pillar.description;
    editForm.weight = String(pillar.weight);
    editForm.clearErrors();
};

const cancelEdit = () => {
    editingId.value = null;
    editForm.reset();
};

const submitCreate = () => {
    createForm.post(route('settings.pillars.store'), {
        onSuccess: () => createForm.reset(),
    });
};

const submitEdit = (pillar: ContentPillar) => {
    editForm.put(route('settings.pillars.update', pillar.id), {
        onSuccess: () => cancelEdit(),
    });
};

const destroy = (pillar: ContentPillar) => {
    if (confirm(`¿Eliminar el pilar «${pillar.name}»?`)) {
        router.delete(route('settings.pillars.destroy', pillar.id));
    }
};
</script>

<template>
    <Head title="Pilares de contenido" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Configuración · Pilares de contenido
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900">Pilares</h3>
                        <span
                            class="text-sm"
                            :class="totalWeight === 100 ? 'text-green-600' : 'text-red-600'"
                        >
                            Suma de pesos: {{ totalWeight }} / 100
                        </span>
                    </div>

                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b text-gray-500">
                                <th class="py-2 pe-4">Nombre</th>
                                <th class="py-2 pe-4">Descripción</th>
                                <th class="py-2 pe-4 w-24">Peso</th>
                                <th class="py-2 w-40"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="pillar in pillars"
                                :key="pillar.id"
                                class="border-b last:border-0"
                            >
                                <template v-if="editingId === pillar.id">
                                    <td class="py-2 pe-4">
                                        <TextInput v-model="editForm.name" type="text" class="w-full" />
                                        <InputError :message="editForm.errors.name" class="mt-1" />
                                    </td>
                                    <td class="py-2 pe-4">
                                        <TextInput v-model="editForm.description" type="text" class="w-full" />
                                        <InputError :message="editForm.errors.description" class="mt-1" />
                                    </td>
                                    <td class="py-2 pe-4">
                                        <TextInput
                                            v-model="editForm.weight"
                                            type="number"
                                            min="0"
                                            max="100"
                                            class="w-full"
                                        />
                                        <InputError :message="editForm.errors.weight" class="mt-1" />
                                    </td>
                                    <td class="py-2">
                                        <div class="flex gap-2">
                                            <PrimaryButton
                                                :disabled="editForm.processing"
                                                @click="submitEdit(pillar)"
                                            >
                                                Guardar
                                            </PrimaryButton>
                                            <SecondaryButton @click="cancelEdit">
                                                Cancelar
                                            </SecondaryButton>
                                        </div>
                                    </td>
                                </template>
                                <template v-else>
                                    <td class="py-2 pe-4 font-medium text-gray-900">
                                        {{ pillar.name }}
                                    </td>
                                    <td class="py-2 pe-4 text-gray-600">
                                        {{ pillar.description }}
                                    </td>
                                    <td class="py-2 pe-4">{{ pillar.weight }}%</td>
                                    <td class="py-2">
                                        <div class="flex gap-2">
                                            <SecondaryButton @click="startEdit(pillar)">
                                                Editar
                                            </SecondaryButton>
                                            <DangerButton @click="destroy(pillar)">
                                                Eliminar
                                            </DangerButton>
                                        </div>
                                    </td>
                                </template>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                    <h3 class="text-lg font-medium text-gray-900">Nuevo pilar</h3>
                    <form
                        @submit.prevent="submitCreate"
                        class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-4"
                    >
                        <div>
                            <InputLabel for="pillar_name" value="Nombre" />
                            <TextInput
                                id="pillar_name"
                                v-model="createForm.name"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="createForm.errors.name" class="mt-1" />
                        </div>
                        <div class="sm:col-span-2">
                            <InputLabel for="pillar_description" value="Descripción" />
                            <TextInput
                                id="pillar_description"
                                v-model="createForm.description"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="createForm.errors.description" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="pillar_weight" value="Peso (%)" />
                            <TextInput
                                id="pillar_weight"
                                v-model="createForm.weight"
                                type="number"
                                min="0"
                                max="100"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="createForm.errors.weight" class="mt-1" />
                        </div>
                        <div class="sm:col-span-4">
                            <PrimaryButton :disabled="createForm.processing">
                                Agregar pilar
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
