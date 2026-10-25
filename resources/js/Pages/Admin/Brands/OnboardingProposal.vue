<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

interface ProposalPillar {
    name: string;
    description: string;
    weight: number;
}

interface ProposalStrategy {
    network: string;
    posts_per_week: number;
    formats: string[];
    focus: string;
}

interface OnboardingProposal {
    error?: string;
    generated_at?: string;
    description?: string;
    tone_of_voice?: string;
    audience?: string;
    cta?: string;
    base_hashtags?: string[];
    pillars?: ProposalPillar[];
    strategies?: ProposalStrategy[];
    palette?: Record<string, string>;
    applied?: Record<string, boolean>;
}

const props = withDefaults(
    defineProps<{
        brandId: number;
        proposal: OnboardingProposal;
        onboardedAt?: string | null;
        planNetworks: string[];
        allowedFormats: Record<string, string[]>;
        pillarsCount: number;
        /** Modo solo lectura/edición: oculta los botones "Aplicar X" y el aviso. */
        compact?: boolean;
    }>(),
    { compact: false },
);

const applied = computed(() => props.proposal.applied ?? {});

const paletteFields = [
    { key: 'dark', label: 'Fondo oscuro' },
    { key: 'light', label: 'Fondo claro' },
    { key: 'accent', label: 'Acento' },
    { key: 'secondary', label: 'Secundario' },
];

// --- Perfil ---------------------------------------------------------------

const profileForm = useForm({
    description: props.proposal.description ?? '',
    tone_of_voice: props.proposal.tone_of_voice ?? '',
    audience: props.proposal.audience ?? '',
    cta: props.proposal.cta ?? '',
    base_hashtags: (props.proposal.base_hashtags ?? []).join(' '),
});

const applyProfile = () => {
    profileForm
        .transform((data) => ({
            ...data,
            base_hashtags: data.base_hashtags
                .split(/[\s,]+/)
                .map((t) => t.trim())
                .filter(Boolean),
        }))
        .post(route('brands.onboarding.apply.profile', { brand: props.brandId }), {
            preserveScroll: true,
        });
};

// --- Pilares ---------------------------------------------------------------

const pillarsForm = useForm({
    pillars: (props.proposal.pillars ?? []).map((p) => ({
        ...p,
        weight: String(p.weight),
    })),
    replace: false,
});

const pillarsSum = computed(() =>
    pillarsForm.pillars.reduce((sum, p) => sum + (Number(p.weight) || 0), 0)
);

const addPillar = () => {
    if (pillarsForm.pillars.length < 6) {
        pillarsForm.pillars.push({ name: '', description: '', weight: '0' });
    }
};

const removePillar = (index: number) => {
    pillarsForm.pillars.splice(index, 1);
};

const applyPillars = () => {
    if (pillarsSum.value !== 100) return;

    pillarsForm.replace = false;
    if (props.pillarsCount > 0) {
        if (!confirm(`Se reemplazarán los ${props.pillarsCount} pilares existentes.`)) {
            return;
        }
        pillarsForm.replace = true;
    }

    pillarsForm
        .transform((data) => ({
            ...data,
            pillars: data.pillars.map((p) => ({ ...p, weight: Number(p.weight) || 0 })),
        }))
        .post(
            route('brands.onboarding.apply.pillars', { brand: props.brandId }),
            { preserveScroll: true, onFinish: () => (pillarsForm.replace = false) }
        );
};

// --- Estrategias ------------------------------------------------------------

interface StrategyRow extends Omit<ProposalStrategy, 'posts_per_week'> {
    posts_per_week: string;
    inPlan: boolean;
}

const strategiesForm = useForm({
    strategies: (props.proposal.strategies ?? []).map((s) => ({
        ...s,
        posts_per_week: String(s.posts_per_week),
        formats: [...s.formats],
        inPlan: props.planNetworks.includes(s.network),
    })) as StrategyRow[],
});

const toggleFormat = (strategy: StrategyRow, format: string) => {
    const i = strategy.formats.indexOf(format);
    if (i === -1) {
        strategy.formats.push(format);
    } else {
        strategy.formats.splice(i, 1);
    }
};

const applyStrategies = () => {
    strategiesForm
        .transform((data) => ({
            strategies: data.strategies
                .filter((s) => s.inPlan)
                .map(({ inPlan, ...s }) => ({
                    ...s,
                    posts_per_week: Number(s.posts_per_week) || 1,
                })),
        }))
        .post(
            route('brands.onboarding.apply.strategies', { brand: props.brandId }),
            { preserveScroll: true }
        );
};

// --- Paleta -----------------------------------------------------------------

const paletteForm = useForm({
    palette: {
        dark: props.proposal.palette?.dark ?? '#1A2A42',
        light: props.proposal.palette?.light ?? '#F8F5F0',
        accent: props.proposal.palette?.accent ?? '#27C2A5',
        secondary: props.proposal.palette?.secondary ?? '#7854D2',
    } as Record<string, string>,
});

const applyPalette = () => {
    paletteForm.post(
        route('brands.onboarding.apply.palette', { brand: props.brandId }),
        { preserveScroll: true }
    );
};
</script>

<template>
    <div class="mt-6 space-y-6">
        <div
            v-if="onboardedAt && !compact"
            class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700 dark:border-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300"
        >
            Marca configurada con ayuda de IA ✓ ({{ onboardedAt }})
        </div>
        <div
            v-else-if="!compact"
            class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-300"
        >
            Propuesta generada el {{ proposal.generated_at ?? '—' }} — revisa y aplica
            cada sección. Nada se guarda hasta que pulses su botón de aplicar.
        </div>

        <!-- Perfil -->
        <section class="overflow-hidden bg-surface p-6 shadow-sm sm:rounded-lg">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-semibold text-fg">Perfil de marca</h3>
                <span v-if="applied.profile" class="text-xs font-medium text-emerald-600 dark:text-emerald-400">
                    Aplicada ✓
                </span>
            </div>
            <div class="mt-4 space-y-4">
                <div>
                    <InputLabel for="ob_description" value="Descripción (qué hace, para quién)" />
                    <textarea
                        id="ob_description"
                        v-model="profileForm.description"
                        rows="3"
                        maxlength="300"
                        class="mt-1 block w-full rounded-md border-line-strong bg-surface text-fg shadow-sm focus:border-brand-primary focus:ring-brand-primary"
                    ></textarea>
                    <InputError :message="profileForm.errors.description" class="mt-1" />
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="ob_tone" value="Tono de voz" />
                        <TextInput id="ob_tone" v-model="profileForm.tone_of_voice" class="mt-1 block w-full" />
                        <InputError :message="profileForm.errors.tone_of_voice" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="ob_audience" value="Audiencia" />
                        <TextInput id="ob_audience" v-model="profileForm.audience" class="mt-1 block w-full" />
                        <InputError :message="profileForm.errors.audience" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="ob_cta" value="Llamado a la acción" />
                        <TextInput id="ob_cta" v-model="profileForm.cta" class="mt-1 block w-full" />
                        <InputError :message="profileForm.errors.cta" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="ob_hashtags" value="Hashtags base (máx. 8)" />
                        <TextInput
                            id="ob_hashtags"
                            v-model="profileForm.base_hashtags"
                            class="mt-1 block w-full font-mono text-sm"
                            placeholder="#marca #sector"
                        />
                        <InputError :message="profileForm.errors.base_hashtags" class="mt-1" />
                    </div>
                </div>
                <div v-if="!compact" class="flex justify-end">
                    <PrimaryButton :disabled="profileForm.processing" @click="applyProfile">
                        Aplicar perfil
                    </PrimaryButton>
                </div>
            </div>
        </section>

        <!-- Pilares -->
        <section class="overflow-hidden bg-surface p-6 shadow-sm sm:rounded-lg">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-semibold text-fg">Pilares de contenido</h3>
                <div class="flex items-center gap-3">
                    <span
                        class="text-sm"
                        :class="pillarsSum === 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'"
                    >
                        Suma: {{ pillarsSum }} / 100
                    </span>
                    <span v-if="applied.pillars" class="text-xs font-medium text-emerald-600 dark:text-emerald-400">
                        Aplicada ✓
                    </span>
                </div>
            </div>

            <p v-if="pillarsCount > 0" class="mt-1 text-sm text-amber-600 dark:text-amber-400">
                La marca ya tiene {{ pillarsCount }} pilares: aplicar los reemplazará.
            </p>
            <InputError :message="pillarsForm.errors.pillars" class="mt-1" />

            <div class="mt-3 overflow-x-auto">
            <table class="w-full min-w-[30rem] text-left text-sm">
                <thead>
                    <tr class="border-b border-line text-muted">
                        <th class="py-2 pe-4 w-52">Nombre</th>
                        <th class="py-2 pe-4">Descripción</th>
                        <th class="py-2 pe-4 w-24">Peso</th>
                        <th class="py-2 w-16"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(pillar, i) in pillarsForm.pillars"
                        :key="i"
                        class="border-b border-line last:border-0"
                    >
                        <td class="py-2 pe-4">
                            <TextInput v-model="pillar.name" class="w-full" />
                        </td>
                        <td class="py-2 pe-4">
                            <TextInput v-model="pillar.description" class="w-full" />
                        </td>
                        <td class="py-2 pe-4">
                            <TextInput v-model="pillar.weight" type="number" min="0" max="100" class="w-full" />
                        </td>
                        <td class="py-2">
                            <button
                                type="button"
                                class="text-xs text-red-600 hover:underline dark:text-red-400"
                                @click="removePillar(i)"
                            >
                                Quitar
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
            </div>

            <div class="mt-4 flex items-center justify-between">
                <SecondaryButton
                    :disabled="pillarsForm.pillars.length >= 6"
                    @click="addPillar"
                >
                    + Añadir pilar
                </SecondaryButton>
                <PrimaryButton
                    v-if="!compact"
                    :disabled="pillarsForm.processing || pillarsSum !== 100 || pillarsForm.pillars.length === 0"
                    @click="applyPillars"
                >
                    Aplicar pilares
                </PrimaryButton>
            </div>
        </section>

        <!-- Estrategias por red -->
        <section class="overflow-hidden bg-surface p-6 shadow-sm sm:rounded-lg">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-semibold text-fg">Estrategias por red</h3>
                <span v-if="applied.strategies" class="text-xs font-medium text-emerald-600 dark:text-emerald-400">
                    Aplicada ✓
                </span>
            </div>
            <p class="mt-1 text-sm text-muted">
                Solo se aplican las redes incluidas en el plan de la marca.
            </p>

            <div class="mt-3 overflow-x-auto">
            <table class="w-full min-w-[34rem] text-left text-sm">
                <thead>
                    <tr class="border-b border-line text-muted">
                        <th class="py-2 pe-4 w-28">Red</th>
                        <th class="py-2 pe-4 w-28">Posts/sem</th>
                        <th class="py-2 pe-4">Formatos</th>
                        <th class="py-2">Enfoque</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="strategy in strategiesForm.strategies"
                        :key="strategy.network"
                        class="border-b border-line last:border-0"
                        :class="{ 'opacity-50': !strategy.inPlan }"
                    >
                        <td class="py-2 pe-4 font-medium text-fg">
                            {{ strategy.network }}
                            <span v-if="!strategy.inPlan" class="block text-xs text-muted">
                                fuera del plan
                            </span>
                        </td>
                        <td class="py-2 pe-4">
                            <TextInput
                                v-model="strategy.posts_per_week"
                                type="number"
                                min="1"
                                max="7"
                                class="w-full"
                                :disabled="!strategy.inPlan"
                            />
                        </td>
                        <td class="py-2 pe-4">
                            <label
                                v-for="format in allowedFormats[strategy.network] ?? []"
                                :key="format"
                                class="me-3 inline-flex items-center gap-1 text-fg-2"
                            >
                                <input
                                    type="checkbox"
                                    class="rounded border-line-strong"
                                    :checked="strategy.formats.includes(format)"
                                    :disabled="!strategy.inPlan"
                                    @change="toggleFormat(strategy, format)"
                                />
                                {{ format }}
                            </label>
                        </td>
                        <td class="py-2">
                            <TextInput
                                v-model="strategy.focus"
                                class="w-full"
                                :disabled="!strategy.inPlan"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
            </div>
            <InputError :message="strategiesForm.errors.strategies" class="mt-1" />

            <div v-if="!compact" class="mt-4 flex justify-end">
                <PrimaryButton
                    :disabled="strategiesForm.processing || !strategiesForm.strategies.some((s) => s.inPlan)"
                    @click="applyStrategies"
                >
                    Aplicar estrategias
                </PrimaryButton>
            </div>
        </section>

        <!-- Paleta -->
        <section class="overflow-hidden bg-surface p-6 shadow-sm sm:rounded-lg">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-semibold text-fg">Paleta</h3>
                <span v-if="applied.palette" class="text-xs font-medium text-emerald-600 dark:text-emerald-400">
                    Aplicada ✓
                </span>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div v-for="field in paletteFields" :key="field.key">
                    <InputLabel :for="`ob-palette-${field.key}`" :value="field.label" />
                    <div class="mt-1 flex items-center gap-2">
                        <input
                            :id="`ob-palette-${field.key}`"
                            v-model="paletteForm.palette[field.key]"
                            type="color"
                            class="h-9 w-12 cursor-pointer rounded border border-line-strong"
                        />
                        <TextInput
                            v-model="paletteForm.palette[field.key]"
                            class="block w-full font-mono text-sm"
                        />
                    </div>
                    <InputError :message="paletteForm.errors[`palette.${field.key}`]" class="mt-1" />
                </div>
            </div>
            <div v-if="!compact" class="mt-4 flex justify-end">
                <PrimaryButton :disabled="paletteForm.processing" @click="applyPalette">
                    Aplicar paleta
                </PrimaryButton>
            </div>
        </section>
    </div>
</template>
