<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

// Tu marca: nombre, web, plan y aprobación → onboarding.brand.store.
// Si ya existe (vuelta atrás), el paso queda en modo edición.
const props = defineProps<{
    brand?: Record<string, any> | null;
    plans: { id: number; name: string; price_label: string; posts_per_month: number | null }[];
}>();

const emit = defineEmits<{ gate: [v: boolean] }>();

const form = useForm({
    name: props.brand?.name ?? '',
    website_url: props.brand?.website_url ?? '',
    plan_id: props.brand?.plan_id ?? props.plans[0]?.id ?? null,
    requires_client_approval: props.brand?.requires_client_approval ?? true,
});

const submit = () => form.post('/onboarding/brand', { preserveScroll: true });

// Continuar solo con marca creada (la orquesta Index; aquí solo avisamos
// de que sin marca no se puede seguir).
watch(() => props.brand, (b) => emit('gate', !!b), { immediate: true });
</script>

<template>
    <div>
        <h2 class="text-2xl font-bold text-fg">Tu marca</h2>
        <p class="mt-2 text-fg-2">
            Los datos básicos; la IA se encargará del resto en el siguiente paso.
        </p>

        <form class="mt-6 space-y-5 rounded-lg bg-surface p-6 shadow-sm ring-1 ring-line" @submit.prevent="submit">
            <div>
                <InputLabel for="ob_name" value="Nombre de la marca" />
                <TextInput id="ob_name" v-model="form.name" class="mt-1 block w-full" required />
                <InputError :message="form.errors.name" class="mt-1" />
            </div>
            <div>
                <InputLabel for="ob_web" value="Sitio web (la IA lo leerá)" />
                <TextInput
                    id="ob_web"
                    v-model="form.website_url"
                    type="url"
                    class="mt-1 block w-full"
                    placeholder="https://tumarca.com"
                />
                <InputError :message="form.errors.website_url" class="mt-1" />
            </div>
            <div>
                <InputLabel for="ob_plan" value="Plan" />
                <select
                    id="ob_plan"
                    v-model="form.plan_id"
                    class="mt-1 block w-full rounded-md border-line-strong bg-surface text-fg shadow-sm focus:border-brand-primary focus:ring-brand-primary"
                >
                    <option v-for="p in plans" :key="p.id" :value="p.id">
                        {{ p.name }}{{ p.price_label ? ` — ${p.price_label}` : '' }}
                        {{ p.posts_per_month ? `(hasta ${p.posts_per_month} posts/mes)` : '(ilimitado)' }}
                    </option>
                </select>
                <InputError :message="form.errors.plan_id" class="mt-1" />
            </div>
            <label class="flex items-center gap-2 text-sm text-fg-2">
                <input
                    v-model="form.requires_client_approval"
                    type="checkbox"
                    class="rounded border-line-strong bg-surface text-brand-primary focus:ring-brand-primary"
                />
                El cliente aprueba el contenido antes de publicarse
            </label>

            <div class="flex justify-end">
                <PrimaryButton :disabled="form.processing">
                    {{ brand ? 'Guardar cambios' : 'Crear marca' }}
                </PrimaryButton>
            </div>
        </form>

        <p v-if="brand" class="mt-3 text-sm text-emerald-600 dark:text-emerald-400">
            Marca «{{ brand.name }}» lista — puedes continuar.
        </p>
    </div>
</template>
