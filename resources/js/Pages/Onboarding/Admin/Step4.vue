<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

// Identidad: logo + paleta aplicada + vista previa real (iframe a
// onboarding.preview, que renderiza la plantilla instagram-quote).
const props = defineProps<{ brand: Record<string, any> | null }>();
const emit = defineEmits<{ next: [] }>();

const paletteFields = [
    { key: 'dark', label: 'Oscuro' },
    { key: 'light', label: 'Claro' },
    { key: 'accent', label: 'Acento' },
    { key: 'secondary', label: 'Secundario' },
];

// Reusa brands.update con _method=put + el resto de campos requeridos.
const form = useForm<Record<string, any>>({
    name: props.brand?.name ?? '',
    website_url: props.brand?.website_url ?? '',
    plan_id: props.brand?.plan_id ?? null,
    palette: props.brand?.palette ?? {},
    requires_client_approval: props.brand?.requires_client_approval ?? true,
    logo: null as File | null,
});

const previewKey = ref(0);
const onLogo = (e: Event) => {
    form.logo = (e.target as HTMLInputElement).files?.[0] ?? null;
};
const upload = () =>
    form
        .transform((d) => ({ ...d, _method: 'put' }))
        .post(route('brands.update', { brand: props.brand?.id }), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => previewKey.value++,
        });
</script>

<template>
    <div v-if="brand">
        <h2 class="text-2xl font-bold text-fg">Identidad</h2>
        <p class="mt-2 text-fg-2">Logo, paleta y cómo se verá un post de tu marca.</p>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <div class="space-y-5 rounded-lg bg-surface p-6 shadow-sm ring-1 ring-line">
                <div>
                    <InputLabel for="ob_logo" value="Logo (SVG o PNG, máx. 1 MB)" />
                    <div class="mt-2 flex items-center gap-3">
                        <img :src="brand.logo_url" :alt="brand.name" class="h-10 w-10 object-contain" />
                        <input
                            id="ob_logo"
                            type="file"
                            accept=".svg,.png,image/svg+xml,image/png"
                            class="block w-full text-sm text-muted file:me-3 file:rounded-md file:border-0 file:bg-surface-3 file:px-3 file:py-1.5 file:text-sm file:text-fg"
                            @change="onLogo"
                        />
                    </div>
                    <InputError :message="form.errors.logo" class="mt-1" />
                    <div class="mt-2 flex justify-end">
                        <SecondaryButton :disabled="!form.logo || form.processing" @click="upload">
                            Subir logo
                        </SecondaryButton>
                    </div>
                </div>

                <div>
                    <InputLabel value="Paleta" />
                    <div class="mt-2 grid grid-cols-2 gap-3">
                        <div v-for="f in paletteFields" :key="f.key" class="flex items-center gap-2">
                            <span
                                class="h-8 w-8 rounded border border-line"
                                :style="{ backgroundColor: brand.palette?.[f.key] }"
                            />
                            <div class="text-xs">
                                <div class="text-fg">{{ f.label }}</div>
                                <div class="font-mono text-muted">{{ brand.palette?.[f.key] }}</div>
                            </div>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-muted">
                        ¿Otro color? Vuelve al paso anterior y edita la propuesta antes de aplicar.
                    </p>
                </div>
            </div>

            <div class="rounded-lg bg-surface p-6 shadow-sm ring-1 ring-line">
                <div class="flex items-center justify-between">
                    <InputLabel value="Vista previa" />
                    <button
                        type="button"
                        class="text-xs text-muted underline hover:text-fg"
                        @click="previewKey++"
                    >
                        Actualizar vista previa
                    </button>
                </div>
                <div
                    class="mt-3 overflow-hidden rounded border border-line bg-surface-3"
                    style="width: 421px; height: 526px"
                >
                    <iframe
                        :key="previewKey"
                        :src="route('onboarding.preview')"
                        title="Vista previa del diseño"
                        class="origin-top-left border-0"
                        style="width: 1080px; height: 1350px; transform: scale(0.39)"
                    />
                </div>
            </div>
        </div>
    </div>
    <p v-else class="text-sm text-muted">Primero crea tu marca (paso 2).</p>
</template>
