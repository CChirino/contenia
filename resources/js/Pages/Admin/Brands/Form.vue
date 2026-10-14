<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    brand: {
        id: number;
        name: string;
        slug: string;
        website_url: string | null;
        palette: Record<string, string> | null;
        requires_client_approval: boolean;
        plan_id: number | null;
        logo_url: string;
    } | null;
    plans: { id: number; name: string; price_label: string | null }[];
}>();

const paletteFields = [
    { key: 'dark', label: 'Fondo oscuro' },
    { key: 'light', label: 'Fondo claro' },
    { key: 'accent', label: 'Acento' },
    { key: 'secondary', label: 'Secundario' },
];

const defaults: Record<string, string> = {
    dark: '#1A2A42',
    light: '#F8F5F0',
    accent: '#27C2A5',
    secondary: '#7854D2',
};

const form = useForm<{
    name: string;
    website_url: string;
    plan_id: number | null;
    palette: Record<string, string>;
    requires_client_approval: boolean;
    logo: File | null;
}>({
    name: props.brand?.name ?? '',
    website_url: props.brand?.website_url ?? '',
    plan_id: props.brand?.plan_id ?? props.plans[0]?.id ?? null,
    palette: { ...defaults, ...(props.brand?.palette ?? {}) },
    requires_client_approval: props.brand?.requires_client_approval ?? true,
    logo: null,
});

const submit = () => {
    if (props.brand) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(
            route('brands.update', { brand: props.brand.id }),
            { forceFormData: true }
        );
    } else {
        form.post(route('brands.store'), { forceFormData: true });
    }
};

const onLogo = (event: Event) => {
    form.logo = (event.target as HTMLInputElement).files?.[0] ?? null;
};
</script>

<template>
    <Head :title="brand ? 'Editar marca' : 'Nueva marca'" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Administración · {{ brand ? `Editar ${brand.name}` : 'Nueva marca' }}
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
                        <p v-if="brand" class="mt-1 text-sm text-gray-500">Slug: {{ brand.slug }}</p>
                        <InputError :message="form.errors.name" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="website_url" value="Sitio web" />
                        <TextInput id="website_url" v-model="form.website_url" class="mt-1 block w-full" placeholder="https://" />
                        <InputError :message="form.errors.website_url" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="plan_id" value="Plan" />
                        <select
                            id="plan_id"
                            v-model="form.plan_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                                {{ plan.name }}<template v-if="plan.price_label"> · {{ plan.price_label }}</template>
                            </option>
                        </select>
                        <InputError :message="form.errors.plan_id" class="mt-2" />
                    </div>

                    <fieldset>
                        <legend class="text-sm font-medium text-gray-700">Paleta</legend>
                        <div class="mt-2 grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <div v-for="field in paletteFields" :key="field.key">
                                <InputLabel :for="`palette-${field.key}`" :value="field.label" />
                                <div class="mt-1 flex items-center gap-2">
                                    <input
                                        :id="`palette-${field.key}`"
                                        v-model="form.palette[field.key]"
                                        type="color"
                                        class="h-9 w-12 cursor-pointer rounded border border-gray-300"
                                    />
                                    <TextInput
                                        v-model="form.palette[field.key]"
                                        class="block w-full font-mono text-sm"
                                    />
                                </div>
                                <InputError :message="form.errors[`palette.${field.key}`]" class="mt-1" />
                            </div>
                        </div>
                    </fieldset>

                    <div>
                        <InputLabel for="logo" value="Logo (SVG o PNG, máx. 1 MB)" />
                        <div class="mt-1 flex items-center gap-4">
                            <img v-if="brand" :src="brand.logo_url" :alt="brand.name" class="h-10 w-10 object-contain" />
                            <input
                                id="logo"
                                type="file"
                                accept=".svg,.png"
                                class="text-sm text-gray-600"
                                @change="onLogo"
                            />
                        </div>
                        <InputError :message="form.errors.logo" class="mt-2" />
                    </div>

                    <label class="flex items-center gap-2">
                        <Checkbox v-model:checked="form.requires_client_approval" />
                        <span class="text-sm text-gray-700">Requiere aprobación del cliente</span>
                    </label>

                    <div class="flex items-center justify-end gap-4">
                        <Link :href="route('brands.index')" class="text-sm text-gray-600 hover:text-gray-900">
                            Cancelar
                        </Link>
                        <PrimaryButton :disabled="form.processing">
                            {{ brand ? 'Guardar' : 'Crear marca' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
