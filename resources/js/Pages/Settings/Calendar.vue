<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { CalendarEvent } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useBrandRoute } from '@/composables/useBrandRoute';

const route = useBrandRoute();

const props = defineProps<{
    events: CalendarEvent[];
    isAdmin: boolean;
}>();

const globalEvents = computed(() => props.events.filter((e) => e.brand_id === null));
const brandEvents = computed(() => props.events.filter((e) => e.brand_id !== null));

const editingId = ref<number | null>(null);

const createForm = useForm({
    date: '',
    name: '',
    description: '',
    recurring: false,
    no_posting: false,
    global: false,
});

const editForm = useForm({
    date: '',
    name: '',
    description: '',
    recurring: false,
    no_posting: false,
});

const startEdit = (event: CalendarEvent) => {
    editingId.value = event.id;
    editForm.date = event.date;
    editForm.name = event.name;
    editForm.description = event.description ?? '';
    editForm.recurring = event.recurring;
    editForm.no_posting = event.no_posting;
    editForm.clearErrors();
};

const cancelEdit = () => {
    editingId.value = null;
    editForm.reset();
};

const submitCreate = () => {
    createForm.post(route('settings.calendar.store'), {
        onSuccess: () => createForm.reset(),
    });
};

const submitEdit = (event: CalendarEvent) => {
    editForm.put(route('settings.calendar.update', { event: event.id }), {
        onSuccess: () => cancelEdit(),
    });
};

const destroy = (event: CalendarEvent) => {
    if (confirm(`¿Eliminar el evento «${event.name}»?`)) {
        router.delete(route('settings.calendar.destroy', { event: event.id }));
    }
};
</script>

<template>
    <Head title="Calendario" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-fg">
                Configuración · Calendario
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                <div class="bg-surface p-4 shadow sm:rounded-lg sm:p-8">
                    <h3 class="mb-1 text-lg font-medium text-fg">Feriados (globales)</h3>
                    <p class="mb-4 text-sm text-muted">
                        Compartidos por todas las marcas.<template v-if="!isAdmin"> Solo lectura.</template>
                    </p>

                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-line text-muted">
                                <th class="py-2 pe-4 w-36">Fecha</th>
                                <th class="py-2 pe-4">Nombre</th>
                                <th class="py-2 pe-4">Descripción</th>
                                <th class="py-2 pe-4 w-24">Recurrente</th>
                                <th class="py-2 pe-4 w-32">Sin publicación</th>
                                <th v-if="isAdmin" class="py-2 w-40"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="event in globalEvents"
                                :key="event.id"
                                class="border-b border-line last:border-0"
                            >
                                <template v-if="isAdmin && editingId === event.id">
                                    <td class="py-2 pe-4">
                                        <TextInput v-model="editForm.date" type="date" class="w-full" />
                                        <InputError :message="editForm.errors.date" class="mt-1" />
                                    </td>
                                    <td class="py-2 pe-4">
                                        <TextInput v-model="editForm.name" type="text" class="w-full" />
                                        <InputError :message="editForm.errors.name" class="mt-1" />
                                    </td>
                                    <td class="py-2 pe-4">
                                        <TextInput v-model="editForm.description" type="text" class="w-full" />
                                        <InputError :message="editForm.errors.description" class="mt-1" />
                                    </td>
                                    <td class="py-2 pe-4">
                                        <input
                                            type="checkbox"
                                            v-model="editForm.recurring"
                                            class="rounded border-line-strong bg-surface text-sabere-accent shadow-sm focus:ring-sabere-accent dark:ring-offset-surface"
                                        />
                                    </td>
                                    <td class="py-2 pe-4">
                                        <input
                                            type="checkbox"
                                            v-model="editForm.no_posting"
                                            class="rounded border-line-strong bg-surface text-sabere-accent shadow-sm focus:ring-sabere-accent dark:ring-offset-surface"
                                        />
                                    </td>
                                    <td class="py-2">
                                        <div class="flex gap-2">
                                            <PrimaryButton
                                                :disabled="editForm.processing"
                                                @click="submitEdit(event)"
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
                                    <td class="py-2 pe-4">{{ event.date }}</td>
                                    <td class="py-2 pe-4 font-medium text-fg">
                                        {{ event.name }}
                                    </td>
                                    <td class="py-2 pe-4 text-fg-2">
                                        {{ event.description }}
                                    </td>
                                    <td class="py-2 pe-4">{{ event.recurring ? 'Sí' : 'No' }}</td>
                                    <td class="py-2 pe-4">{{ event.no_posting ? 'Sí' : 'No' }}</td>
                                    <td v-if="isAdmin" class="py-2">
                                        <div class="flex gap-2">
                                            <SecondaryButton @click="startEdit(event)">
                                                Editar
                                            </SecondaryButton>
                                            <DangerButton @click="destroy(event)">
                                                Eliminar
                                            </DangerButton>
                                        </div>
                                    </td>
                                </template>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="bg-surface p-4 shadow sm:rounded-lg sm:p-8">
                    <h3 class="mb-4 text-lg font-medium text-fg">Eventos de la marca</h3>

                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-line text-muted">
                                <th class="py-2 pe-4 w-36">Fecha</th>
                                <th class="py-2 pe-4">Nombre</th>
                                <th class="py-2 pe-4">Descripción</th>
                                <th class="py-2 pe-4 w-24">Recurrente</th>
                                <th class="py-2 pe-4 w-32">Sin publicación</th>
                                <th class="py-2 w-40"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="event in brandEvents"
                                :key="event.id"
                                class="border-b border-line last:border-0"
                            >
                                <template v-if="editingId === event.id">
                                    <td class="py-2 pe-4">
                                        <TextInput v-model="editForm.date" type="date" class="w-full" />
                                        <InputError :message="editForm.errors.date" class="mt-1" />
                                    </td>
                                    <td class="py-2 pe-4">
                                        <TextInput v-model="editForm.name" type="text" class="w-full" />
                                        <InputError :message="editForm.errors.name" class="mt-1" />
                                    </td>
                                    <td class="py-2 pe-4">
                                        <TextInput v-model="editForm.description" type="text" class="w-full" />
                                        <InputError :message="editForm.errors.description" class="mt-1" />
                                    </td>
                                    <td class="py-2 pe-4">
                                        <input
                                            type="checkbox"
                                            v-model="editForm.recurring"
                                            class="rounded border-line-strong bg-surface text-sabere-accent shadow-sm focus:ring-sabere-accent dark:ring-offset-surface"
                                        />
                                    </td>
                                    <td class="py-2 pe-4">
                                        <input
                                            type="checkbox"
                                            v-model="editForm.no_posting"
                                            class="rounded border-line-strong bg-surface text-sabere-accent shadow-sm focus:ring-sabere-accent dark:ring-offset-surface"
                                        />
                                    </td>
                                    <td class="py-2">
                                        <div class="flex gap-2">
                                            <PrimaryButton
                                                :disabled="editForm.processing"
                                                @click="submitEdit(event)"
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
                                    <td class="py-2 pe-4">{{ event.date }}</td>
                                    <td class="py-2 pe-4 font-medium text-fg">
                                        {{ event.name }}
                                    </td>
                                    <td class="py-2 pe-4 text-fg-2">
                                        {{ event.description }}
                                    </td>
                                    <td class="py-2 pe-4">{{ event.recurring ? 'Sí' : 'No' }}</td>
                                    <td class="py-2 pe-4">{{ event.no_posting ? 'Sí' : 'No' }}</td>
                                    <td class="py-2">
                                        <div class="flex gap-2">
                                            <SecondaryButton @click="startEdit(event)">
                                                Editar
                                            </SecondaryButton>
                                            <DangerButton @click="destroy(event)">
                                                Eliminar
                                            </DangerButton>
                                        </div>
                                    </td>
                                </template>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="bg-surface p-4 shadow sm:rounded-lg sm:p-8">
                    <h3 class="text-lg font-medium text-fg">Nuevo evento</h3>
                    <form
                        @submit.prevent="submitCreate"
                        class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3"
                    >
                        <div>
                            <InputLabel for="event_date" value="Fecha" />
                            <TextInput
                                id="event_date"
                                v-model="createForm.date"
                                type="date"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="createForm.errors.date" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="event_name" value="Nombre" />
                            <TextInput
                                id="event_name"
                                v-model="createForm.name"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="createForm.errors.name" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="event_description" value="Descripción (opcional)" />
                            <TextInput
                                id="event_description"
                                v-model="createForm.description"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="createForm.errors.description" class="mt-1" />
                        </div>
                        <label class="flex items-center gap-2 text-sm text-fg-2">
                            <input
                                type="checkbox"
                                v-model="createForm.recurring"
                                class="rounded border-line-strong bg-surface text-sabere-accent shadow-sm focus:ring-sabere-accent dark:ring-offset-surface"
                            />
                            Recurrente cada año
                        </label>
                        <label class="flex items-center gap-2 text-sm text-fg-2">
                            <input
                                type="checkbox"
                                v-model="createForm.no_posting"
                                class="rounded border-line-strong bg-surface text-sabere-accent shadow-sm focus:ring-sabere-accent dark:ring-offset-surface"
                            />
                            Sin publicación
                        </label>
                        <label v-if="isAdmin" class="flex items-center gap-2 text-sm text-fg-2">
                            <input
                                type="checkbox"
                                v-model="createForm.global"
                                class="rounded border-line-strong bg-surface text-sabere-accent shadow-sm focus:ring-sabere-accent dark:ring-offset-surface"
                            />
                            Feriado global (todas las marcas)
                        </label>
                        <div>
                            <PrimaryButton :disabled="createForm.processing">
                                Agregar evento
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
