<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Contact {
    id: number;
    name: string;
    email: string;
    whatsapp: string | null;
}

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
        contacts?: Contact[];
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

const contacts = computed(() => props.brand?.contacts ?? []);

const contactForm = useForm({ name: '', email: '', whatsapp: '' });

const addContact = () => {
    if (!props.brand) return;
    contactForm.post(
        route('brands.contacts.store', { brand: props.brand.id }),
        {
            preserveScroll: true,
            onSuccess: () => contactForm.reset(),
        }
    );
};

const removeContact = (contact: Contact) => {
    if (!props.brand) return;
    router.delete(
        route('brands.contacts.destroy', {
            brand: props.brand.id,
            contact: contact.id,
        }),
        { preserveScroll: true }
    );
};
</script>

<template>
    <Head :title="brand ? 'Editar marca' : 'Nueva marca'" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-fg">
                Administración · {{ brand ? `Editar ${brand.name}` : 'Nueva marca' }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <form
                    class="space-y-6 overflow-hidden bg-surface p-6 shadow-sm sm:rounded-lg"
                    @submit.prevent="submit"
                >
                    <div>
                        <InputLabel for="name" value="Nombre" />
                        <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required />
                        <p v-if="brand" class="mt-1 text-sm text-muted">Slug: {{ brand.slug }}</p>
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
                            class="mt-1 block w-full rounded-md border-line-strong bg-surface text-fg shadow-sm focus:border-brand-primary focus:ring-brand-primary"
                        >
                            <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                                {{ plan.name }}<template v-if="plan.price_label"> · {{ plan.price_label }}</template>
                            </option>
                        </select>
                        <InputError :message="form.errors.plan_id" class="mt-2" />
                    </div>

                    <fieldset>
                        <legend class="text-sm font-medium text-fg-2">Paleta</legend>
                        <div class="mt-2 grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <div v-for="field in paletteFields" :key="field.key">
                                <InputLabel :for="`palette-${field.key}`" :value="field.label" />
                                <div class="mt-1 flex items-center gap-2">
                                    <input
                                        :id="`palette-${field.key}`"
                                        v-model="form.palette[field.key]"
                                        type="color"
                                        class="h-9 w-12 cursor-pointer rounded border border-line-strong"
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
                                class="text-sm text-fg-2"
                                @change="onLogo"
                            />
                        </div>
                        <InputError :message="form.errors.logo" class="mt-2" />
                    </div>

                    <label class="flex items-center gap-2">
                        <Checkbox v-model:checked="form.requires_client_approval" />
                        <span class="text-sm text-fg-2">Requiere aprobación del cliente</span>
                    </label>

                    <div class="flex items-center justify-end gap-4">
                        <Link :href="route('brands.index')" class="text-sm text-fg-2 hover:text-fg">
                            Cancelar
                        </Link>
                        <PrimaryButton :disabled="form.processing">
                            {{ brand ? 'Guardar' : 'Crear marca' }}
                        </PrimaryButton>
                    </div>
                </form>

                <div
                    v-if="brand"
                    class="mt-6 overflow-hidden bg-surface p-6 shadow-sm sm:rounded-lg"
                >
                    <h3 class="text-base font-semibold text-fg">Contactos aprobadores</h3>
                    <p class="mt-1 text-sm text-muted">
                        Reciben por correo los enlaces de revisión del contenido de esta marca.
                    </p>

                    <div
                        v-if="brand.requires_client_approval && contacts.length === 0"
                        class="mt-3 rounded-md border border-amber-200 bg-amber-50 dark:border-amber-700 dark:bg-amber-900/30 p-3 text-sm text-amber-700 dark:text-amber-300"
                    >
                        Esta marca requiere aprobación del cliente pero no tiene contactos:
                        los enlaces de revisión no llegarán a nadie por correo.
                    </div>

                    <ul v-if="contacts.length" class="mt-4 divide-y divide-line">
                        <li
                            v-for="contact in contacts"
                            :key="contact.id"
                            class="flex items-center gap-4 py-2 text-sm"
                        >
                            <span class="w-40 font-medium text-fg">{{ contact.name }}</span>
                            <span class="text-fg-2">{{ contact.email }}</span>
                            <span class="text-muted">{{ contact.whatsapp ?? '' }}</span>
                            <button
                                type="button"
                                class="ms-auto text-xs text-red-600 dark:text-red-400 hover:underline"
                                @click="removeContact(contact)"
                            >
                                Eliminar
                            </button>
                        </li>
                    </ul>

                    <form
                        class="mt-4 flex flex-wrap items-end gap-3"
                        @submit.prevent="addContact"
                    >
                        <div>
                            <InputLabel for="c_name" value="Nombre" />
                            <TextInput id="c_name" v-model="contactForm.name" class="mt-1 block w-40" required />
                            <InputError :message="contactForm.errors.name" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="c_email" value="Email" />
                            <TextInput id="c_email" v-model="contactForm.email" type="email" class="mt-1 block w-56" required />
                            <InputError :message="contactForm.errors.email" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="c_whatsapp" value="WhatsApp (opcional)" />
                            <TextInput id="c_whatsapp" v-model="contactForm.whatsapp" class="mt-1 block w-36" />
                            <InputError :message="contactForm.errors.whatsapp" class="mt-1" />
                        </div>
                        <PrimaryButton :disabled="contactForm.processing">Añadir</PrimaryButton>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
