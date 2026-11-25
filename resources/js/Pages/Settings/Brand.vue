<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { BrandProfile } from '@/types';
import { normalizePalette } from '@/utils/brandPalette';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useBrandRoute } from '@/composables/useBrandRoute';

const route = useBrandRoute();
const page = usePage();
const activeBrand = computed(() => (page.props as any).brand ?? null);
const canManageBrand = computed(() => ['admin', 'gestor'].includes((page.props.auth as any)?.user?.role ?? ''));
const brandPalette = computed(() => normalizePalette(activeBrand.value?.palette));

const props = defineProps<{
    brandProfile: BrandProfile;
}>();

const hashtagsText = ref(props.brandProfile.base_hashtags.join(', '));

const form = useForm({
    description: props.brandProfile.description,
    tone_of_voice: props.brandProfile.tone_of_voice,
    audience: props.brandProfile.audience,
    base_hashtags: props.brandProfile.base_hashtags,
    cta: props.brandProfile.cta,
    url: props.brandProfile.url,
    // brand_profile.palette queda sin uso: la paleta vive en brands.palette
    // y se edita en Administración → Marcas (solo lectura aquí).
    palette: { ...props.brandProfile.palette },
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        base_hashtags: hashtagsText.value
            .split(',')
            .map((tag) => tag.trim())
            .filter((tag) => tag.length > 0),
    })).put(route('settings.brand.update'));
};
</script>

<template>
    <Head title="Perfil de marca" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-fg">
                Configuración · Perfil de marca
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl space-y-6 sm:px-6 lg:px-8">
                <div class="bg-surface p-4 shadow sm:rounded-lg sm:p-8">
                    <form @submit.prevent="submit" class="space-y-6">
                        <div>
                            <InputLabel for="description" value="Descripción de la marca (qué hace, para quién)" />
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="2"
                                class="mt-1 block w-full rounded-md border-line-strong bg-surface text-fg shadow-sm focus:border-brand-primary focus:ring-brand-primary"
                            />
                            <InputError :message="form.errors.description" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="tone_of_voice" value="Tono de voz" />
                            <textarea
                                id="tone_of_voice"
                                v-model="form.tone_of_voice"
                                rows="3"
                                class="mt-1 block w-full rounded-md border-line-strong bg-surface text-fg shadow-sm focus:border-brand-primary focus:ring-brand-primary"
                            />
                            <InputError :message="form.errors.tone_of_voice" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="audience" value="Audiencia" />
                            <textarea
                                id="audience"
                                v-model="form.audience"
                                rows="3"
                                class="mt-1 block w-full rounded-md border-line-strong bg-surface text-fg shadow-sm focus:border-brand-primary focus:ring-brand-primary"
                            />
                            <InputError :message="form.errors.audience" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="base_hashtags" value="Hashtags base (separados por coma)" />
                            <TextInput
                                id="base_hashtags"
                                v-model="hashtagsText"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="#TuMarca, #TuSector"
                            />
                            <InputError :message="form.errors.base_hashtags" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="cta" value="Llamado a la acción (CTA)" />
                            <TextInput
                                id="cta"
                                v-model="form.cta"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors.cta" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="url" value="URL" />
                            <TextInput
                                id="url"
                                v-model="form.url"
                                type="url"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors.url" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel value="Paleta de colores" />
                            <div class="mt-2 flex flex-wrap items-center gap-3">
                                <span
                                    v-for="(c, i) in brandPalette.primary"
                                    :key="`p-${i}`"
                                    class="inline-flex items-center gap-1.5 rounded-md border border-line px-2 py-1 text-xs"
                                >
                                    <span class="h-4 w-4 rounded" :style="{ backgroundColor: c }" />
                                    Primario {{ i + 1 }} · <span class="font-mono">{{ c }}</span>
                                </span>
                                <span
                                    v-for="(c, i) in brandPalette.secondary"
                                    :key="`s-${i}`"
                                    class="inline-flex items-center gap-1.5 rounded-md border border-line px-2 py-1 text-xs"
                                >
                                    <span class="h-4 w-4 rounded" :style="{ backgroundColor: c }" />
                                    Secundario {{ i + 1 }} · <span class="font-mono">{{ c }}</span>
                                </span>
                            </div>
                            <p v-if="canManageBrand" class="mt-2 text-xs text-muted">
                                <a href="/brands" class="text-brand-primary hover:underline">
                                    Editar en Administración → Marcas
                                </a>
                            </p>
                        </div>

                        <div class="flex items-center gap-4">
                            <PrimaryButton :disabled="form.processing">
                                Guardar
                            </PrimaryButton>
                            <span v-if="form.recentlySuccessful" class="text-sm text-fg-2">
                                Guardado.
                            </span>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
