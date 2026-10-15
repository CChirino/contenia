<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { BrandProfile } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useBrandRoute } from '@/composables/useBrandRoute';

const route = useBrandRoute();

const props = defineProps<{
    brandProfile: BrandProfile;
}>();

const hashtagsText = ref(props.brandProfile.base_hashtags.join(', '));

const form = useForm({
    tone_of_voice: props.brandProfile.tone_of_voice,
    audience: props.brandProfile.audience,
    base_hashtags: props.brandProfile.base_hashtags,
    cta: props.brandProfile.cta,
    url: props.brandProfile.url,
    palette: { ...props.brandProfile.palette },
});

const paletteLabels: Record<string, string> = {
    dark: 'Azul oscuro',
    light: 'Crema',
    accent: 'Teal',
    purple: 'Morado',
};

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
                            <InputLabel for="tone_of_voice" value="Tono de voz" />
                            <textarea
                                id="tone_of_voice"
                                v-model="form.tone_of_voice"
                                rows="3"
                                class="mt-1 block w-full rounded-md border-line-strong bg-surface text-fg shadow-sm focus:border-sabere-accent focus:ring-sabere-accent"
                            />
                            <InputError :message="form.errors.tone_of_voice" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="audience" value="Audiencia" />
                            <textarea
                                id="audience"
                                v-model="form.audience"
                                rows="3"
                                class="mt-1 block w-full rounded-md border-line-strong bg-surface text-fg shadow-sm focus:border-sabere-accent focus:ring-sabere-accent"
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
                                placeholder="#Sabere, #EducacionVenezuela"
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
                            <div class="mt-2 grid grid-cols-2 gap-4 sm:grid-cols-4">
                                <div v-for="(color, key) in form.palette" :key="key">
                                    <label class="block text-sm text-fg-2">
                                        {{ paletteLabels[key] ?? key }}
                                    </label>
                                    <div class="mt-1 flex items-center gap-2">
                                        <input
                                            type="color"
                                            v-model="form.palette[key]"
                                            class="h-9 w-9 rounded border border-line-strong"
                                        />
                                        <TextInput
                                            v-model="form.palette[key]"
                                            type="text"
                                            class="block w-full"
                                        />
                                    </div>
                                </div>
                            </div>
                            <InputError :message="form.errors.palette" class="mt-2" />
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
