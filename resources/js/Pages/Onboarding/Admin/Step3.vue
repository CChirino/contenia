<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import OnboardingProposal from '@/Pages/Admin/Brands/OnboardingProposal.vue';
import { useOnboardingPoll } from '@/composables/useOnboardingPoll';
import { router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

// Deja que la IA lea la marca: genera propuesta (job OnboardBrand),
// polling a onboarding.status, "Aplicar todo" o configuración manual.
const props = defineProps<{
    brand: Record<string, any> | null;
    allowedFormats: Record<string, string[]>;
}>();

const emit = defineEmits<{ gate: [v: boolean] }>();

const status = ref<Record<string, any> | null>(null);
const generating = ref(false);
const manual = ref(false);

const messages = [
    'Leyendo tu web…',
    'Detectando colores…',
    'Proponiendo pilares…',
    'Definiendo estrategia por red…',
];
const messageIndex = ref(0);
let messageTimer: ReturnType<typeof setInterval> | null = null;

const poll = useOnboardingPoll('/onboarding/status', 4000);
watch(poll.data, (d) => {
    if (!d) return;
    status.value = d;
    if (!d.pending && generating.value) {
        generating.value = false;
        stopMessages();
        router.reload({ only: ['brand'] });
    }
});

const startMessages = () => {
    messageIndex.value = 0;
    messageTimer = setInterval(() => {
        messageIndex.value = (messageIndex.value + 1) % messages.length;
    }, 3000);
};
const stopMessages = () => {
    if (messageTimer) {
        clearInterval(messageTimer);
        messageTimer = null;
    }
};

const generateForm = useForm({ sample_posts: '', social_handle: '' });

// Perfil de red social pegado como "sitio web" (marca sin web).
const isSocialUrl = (url: string | null | undefined) =>
    !!url && /(^|\/\/|\.)(instagram|facebook|fb|tiktok|linkedin)\.com/i.test(url);
const generate = () => {
    if (!props.brand) return;
    generating.value = true;
    startMessages();
    generateForm.post(route('brands.onboarding.run', { brand: props.brand.id }), {
        preserveScroll: true,
        onFinish: () => poll.start(),
        onError: () => {
            generating.value = false;
            stopMessages();
        },
    });
};

const applyAllForm = useForm({});
const applyAll = () =>
    applyAllForm.post('/onboarding/apply-all', {
        preserveScroll: true,
        onFinish: () => router.reload({ only: ['brand'] }),
    });

const manualForm = useForm({ description: '', tone_of_voice: '', audience: '' });
const saveManual = () =>
    manualForm.post('/onboarding/manual', {
        preserveScroll: true,
        onFinish: () => router.reload({ only: ['brand'] }),
    });

const proposal = computed(() => status.value?.proposal ?? props.brand?.onboarding_proposal ?? null);
const applied = computed(() => status.value?.applied ?? proposal.value?.applied ?? {});
const pillarsCount = computed(() => status.value?.pillars_count ?? props.brand?.pillars_count ?? 0);
const ready = computed(() =>
    Boolean((applied.value.profile && applied.value.pillars) || pillarsCount.value > 0),
);
watch(ready, (v) => emit('gate', v), { immediate: true });

const pending = computed(() => generating.value || status.value?.pending);
const hasProposal = computed(
    () => proposal.value && !proposal.value.error && (proposal.value.pillars?.length ?? 0) > 0,
);

onMounted(() => {
    if (props.brand) {
        poll.start();
    }
});
</script>

<template>
    <div v-if="brand">
        <h2 class="text-2xl font-bold text-fg">Deja que la IA lea {{ brand.name }}</h2>
        <p class="mt-2 text-fg-2">
            Analiza tu web o tu perfil de Instagram/Facebook y propone perfil, pilares, estrategia por red y paleta.
        </p>

        <!-- Sin propuesta aún -->
        <div
            v-if="!hasProposal"
            class="mt-6 space-y-5 rounded-lg bg-surface p-6 shadow-sm ring-1 ring-line"
        >
            <p
                v-if="!brand.website_url || isSocialUrl(brand.website_url)"
                class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200"
            >
                Sin web: los posts de ejemplo son la mejor fuente para que la IA aprenda el tono.
                Pega 3–5 captions reales de la marca.
            </p>
            <div>
                <InputLabel for="ob_samples" value="Posts de ejemplo (opcional, separados por línea en blanco)" />
                <textarea
                    id="ob_samples"
                    v-model="generateForm.sample_posts"
                    rows="4"
                    class="mt-1 block w-full rounded-md border-line-strong bg-surface text-fg shadow-sm focus:border-brand-primary focus:ring-brand-primary"
                />
                <InputError :message="generateForm.errors.sample_posts" class="mt-1" />
            </div>
            <div>
                <InputLabel for="ob_social" value="Usuario de Instagram o URL de la red (opcional)" />
                <TextInput id="ob_social" v-model="generateForm.social_handle" class="mt-1 block w-full" placeholder="@tumarca" />
            </div>
            <InputError :message="(generateForm.errors as any).website_url" />

            <div v-if="pending" class="space-y-2">
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-surface-3">
                    <div class="h-full w-1/3 animate-pulse rounded-full bg-brand-primary" />
                </div>
                <p class="text-sm text-muted">{{ messages[messageIndex] }}</p>
            </div>

            <div class="flex items-center justify-between">
                <PrimaryButton :disabled="pending || generateForm.processing" @click="generate">
                    Generar propuesta
                </PrimaryButton>
                <button type="button" class="text-sm text-muted underline hover:text-fg" @click="manual = !manual">
                    Configurar a mano
                </button>
            </div>

        </div>

        <!-- Propuesta lista -->
        <div v-if="hasProposal" class="mt-4">
            <OnboardingProposal
                :brand-id="brand.id"
                :proposal="proposal"
                :plan-networks="brand.plan_networks ?? []"
                :allowed-formats="allowedFormats"
                :pillars-count="pillarsCount"
                compact
            />
            <div class="mt-6 flex items-center gap-4">
                <PrimaryButton :disabled="applyAllForm.processing" class="px-6 py-3" @click="applyAll">
                    Aplicar todo
                </PrimaryButton>
                <span v-if="ready" class="text-sm text-emerald-600 dark:text-emerald-400">
                    Perfil y pilares aplicados ✓
                </span>
            </div>
        </div>

        <!-- Error de la IA -->
        <div
            v-if="proposal?.error"
            class="mt-6 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-300"
        >
            {{ proposal.error }}
        </div>

        <!-- Configuración manual -->
        <form
            v-if="manual || proposal?.error"
            class="mt-6 space-y-4 rounded-lg bg-surface p-6 shadow-sm ring-1 ring-line"
            @submit.prevent="saveManual"
        >
            <h3 class="font-semibold text-fg">Configurar a mano</h3>
            <div>
                <InputLabel for="ob_desc" value="Descripción de la marca" />
                <textarea
                    id="ob_desc"
                    v-model="manualForm.description"
                    rows="2"
                    maxlength="300"
                    class="mt-1 block w-full rounded-md border-line-strong bg-surface text-fg shadow-sm focus:border-brand-primary focus:ring-brand-primary"
                />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="ob_tone" value="Tono de voz" />
                    <TextInput id="ob_tone" v-model="manualForm.tone_of_voice" class="mt-1 block w-full" />
                </div>
                <div>
                    <InputLabel for="ob_aud" value="Audiencia" />
                    <TextInput id="ob_aud" v-model="manualForm.audience" class="mt-1 block w-full" />
                </div>
            </div>
            <p class="text-xs text-muted">
                Se crearán 3 pilares genéricos (Educativo 40 / Marca 30 / Comunidad 30) si la marca no tiene.
            </p>
            <div class="flex justify-end">
                <SecondaryButton :disabled="manualForm.processing">Guardar configuración</SecondaryButton>
            </div>
        </form>
    </div>

    <p v-else class="text-sm text-muted">Primero crea tu marca (paso anterior).</p>
</template>
