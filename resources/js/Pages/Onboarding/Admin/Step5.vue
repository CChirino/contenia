<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

// Quién aprueba: contactos de la marca (reusa brands.contacts.*).
const props = defineProps<{ brand: Record<string, any> | null }>();

const contacts = computed(() => props.brand?.contacts ?? []);

const form = useForm({ name: '', email: '', whatsapp: '' });
const add = () =>
    form.post(route('brands.contacts.store', { brand: props.brand?.id }), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
const remove = (id: number) =>
    router.delete(route('brands.contacts.destroy', { brand: props.brand?.id, contact: id }), {
        preserveScroll: true,
    });
</script>

<template>
    <div>
        <h2 class="text-2xl font-bold text-fg">¿Quién aprueba el contenido?</h2>
        <p class="mt-2 text-fg-2">
            Recibirán un enlace de revisión por correo cada mes. Puedes añadir más luego o saltar este paso.
        </p>

        <form
            class="mt-6 space-y-4 rounded-lg bg-surface p-6 shadow-sm ring-1 ring-line"
            @submit.prevent="add"
        >
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <InputLabel for="ob_cname" value="Nombre" />
                    <TextInput id="ob_cname" v-model="form.name" class="mt-1 block w-full" required />
                </div>
                <div>
                    <InputLabel for="ob_cemail" value="Email" />
                    <TextInput id="ob_cemail" v-model="form.email" type="email" class="mt-1 block w-full" required />
                </div>
                <div>
                    <InputLabel for="ob_cwa" value="WhatsApp (opcional)" />
                    <TextInput id="ob_cwa" v-model="form.whatsapp" class="mt-1 block w-full" />
                </div>
            </div>
            <InputError :message="form.errors.email" />
            <div class="flex justify-end">
                <PrimaryButton :disabled="form.processing">Añadir aprobador</PrimaryButton>
            </div>
        </form>

        <ul v-if="contacts.length" class="mt-4 divide-y divide-line rounded-lg bg-surface shadow-sm ring-1 ring-line">
            <li v-for="c in contacts" :key="c.id" class="flex items-center justify-between px-4 py-3 text-sm">
                <span class="text-fg">
                    {{ c.name }} <span class="text-muted">&lt;{{ c.email }}&gt;</span>
                    <span v-if="c.whatsapp" class="text-muted"> · {{ c.whatsapp }}</span>
                </span>
                <button type="button" class="text-red-500 hover:underline" @click="remove(c.id)">
                    Eliminar
                </button>
            </li>
        </ul>
    </div>
</template>
