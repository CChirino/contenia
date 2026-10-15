<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { NetworkStrategy } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { useBrandRoute } from '@/composables/useBrandRoute';

const route = useBrandRoute();

const props = defineProps<{
    strategies: NetworkStrategy[];
    allowedFormats: Record<string, string[]>;
    allowed_networks: string[];
    plan_name: string;
}>();

const isAllowed = (network: string) => props.allowed_networks.includes(network);

const networkLabels: Record<string, string> = {
    instagram: 'Instagram',
    linkedin: 'LinkedIn',
    tiktok: 'TikTok',
};

const formatLabels: Record<string, string> = {
    image: 'Imagen',
    carousel: 'Carrusel',
    reel: 'Reel',
    video: 'Video',
};

const form = useForm({
    strategies: props.strategies.map((s) => ({
        id: s.id,
        network: s.network,
        posts_per_week: String(s.posts_per_week),
        preferred_formats: [...s.preferred_formats],
        focus: s.focus,
    })),
});

const submit = () => {
    form.put(route('settings.networks.update'));
};
</script>

<template>
    <Head title="Estrategias por red" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Configuración · Estrategias por red
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-6">
                    <div
                        v-for="(strategy, index) in form.strategies"
                        :key="strategy.id"
                        class="bg-white p-4 shadow sm:rounded-lg sm:p-8"
                        :class="{ 'opacity-60': !isAllowed(strategy.network) }"
                    >
                        <h3 class="flex items-center gap-3 text-lg font-medium text-gray-900">
                            {{ networkLabels[strategy.network] ?? strategy.network }}
                            <span
                                v-if="!isAllowed(strategy.network)"
                                class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-normal text-gray-500"
                            >
                                No incluida en el plan {{ plan_name }}
                            </span>
                        </h3>

                        <div
                            class="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-3"
                            :class="{ 'pointer-events-none': !isAllowed(strategy.network) }"
                        >
                            <div>
                                <InputLabel
                                    :for="`posts_per_week_${strategy.id}`"
                                    value="Posts por semana"
                                />
                                <TextInput
                                    :id="`posts_per_week_${strategy.id}`"
                                    v-model="strategy.posts_per_week"
                                    type="number"
                                    min="0"
                                    max="14"
                                    :disabled="!isAllowed(strategy.network)"
                                    class="mt-1 block w-full"
                                />
                                <InputError
                                    :message="form.errors[`strategies.${index}.posts_per_week`]"
                                    class="mt-2"
                                />
                            </div>

                            <div>
                                <InputLabel value="Formatos preferidos" />
                                <div class="mt-2 space-y-2">
                                    <label
                                        v-for="format in allowedFormats[strategy.network]"
                                        :key="format"
                                        class="flex items-center gap-2 text-sm text-gray-700"
                                    >
                                        <input
                                            type="checkbox"
                                            v-model="strategy.preferred_formats"
                                            :value="format"
                                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                        />
                                        {{ formatLabels[format] ?? format }}
                                    </label>
                                </div>
                                <InputError
                                    :message="form.errors[`strategies.${index}.preferred_formats`]"
                                    class="mt-2"
                                />
                            </div>

                            <div>
                                <InputLabel :for="`focus_${strategy.id}`" value="Enfoque" />
                                <textarea
                                    :id="`focus_${strategy.id}`"
                                    v-model="strategy.focus"
                                    rows="3"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                <InputError
                                    :message="form.errors[`strategies.${index}.focus`]"
                                    class="mt-2"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <PrimaryButton :disabled="form.processing">
                            Guardar
                        </PrimaryButton>
                        <span v-if="form.recentlySuccessful" class="text-sm text-gray-600">
                            Guardado.
                        </span>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
