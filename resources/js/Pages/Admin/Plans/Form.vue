<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

interface Plan {
    id: number;
    name: string;
    posts_per_month: number | null;
    regenerations_per_month: number | null;
    image_refreshes_per_month: number | null;
    networks: string[];
    price_label: string | null;
}

const props = defineProps<{
    plan: Plan | null;
    networks: string[];
}>();

const networkLabels: Record<string, string> = {
    instagram: 'Instagram',
    linkedin: 'LinkedIn',
    tiktok: 'TikTok',
};

const form = useForm({
    name: props.plan?.name ?? '',
    posts_per_month: props.plan?.posts_per_month?.toString() ?? '',
    regenerations_per_month: props.plan?.regenerations_per_month?.toString() ?? '',
    image_refreshes_per_month: props.plan?.image_refreshes_per_month?.toString() ?? '',
    networks: props.plan?.networks ? [...props.plan.networks] : [],
    price_label: props.plan?.price_label ?? '',
});

const submit = () => {
    if (props.plan) {
        form.put(route('admin.plans.update', { plan: props.plan.id }));
    } else {
        form.post(route('admin.plans.store'));
    }
};

const limits = [
    { key: 'posts_per_month' as const, label: 'Posts generados por mes' },
    { key: 'regenerations_per_month' as const, label: 'Regeneraciones por mes' },
    { key: 'image_refreshes_per_month' as const, label: 'Fondos nuevos por mes' },
];
</script>

<template>
    <Head :title="plan ? `Plan · ${plan.name}` : 'Nuevo plan'" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ plan ? `Editar plan «${plan.name}»` : 'Nuevo plan' }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-6 bg-white p-6 shadow sm:rounded-lg">
                    <div>
                        <InputLabel for="name" value="Nombre" />
                        <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required />
                        <InputError :message="form.errors.name" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                        <div v-for="limit in limits" :key="limit.key">
                            <InputLabel :for="limit.key" :value="limit.label" />
                            <TextInput
                                :id="limit.key"
                                v-model="form[limit.key]"
                                type="number"
                                min="0"
                                placeholder="Ilimitado"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors[limit.key]" class="mt-2" />
                        </div>
                    </div>
                    <p class="-mt-3 text-xs text-gray-500">Vacío = ilimitado.</p>

                    <div>
                        <InputLabel value="Redes incluidas" />
                        <div class="mt-2 flex gap-6">
                            <label
                                v-for="network in networks"
                                :key="network"
                                class="flex items-center gap-2 text-sm text-gray-700"
                            >
                                <input
                                    type="checkbox"
                                    v-model="form.networks"
                                    :value="network"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                />
                                {{ networkLabels[network] ?? network }}
                            </label>
                        </div>
                        <InputError :message="form.errors.networks" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="price_label" value="Etiqueta de precio" />
                        <TextInput
                            id="price_label"
                            v-model="form.price_label"
                            class="mt-1 block w-full"
                            placeholder="Ej.: $49/mes"
                        />
                        <InputError :message="form.errors.price_label" class="mt-2" />
                    </div>

                    <div class="flex items-center gap-4">
                        <PrimaryButton :disabled="form.processing">
                            {{ plan ? 'Guardar' : 'Crear plan' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
