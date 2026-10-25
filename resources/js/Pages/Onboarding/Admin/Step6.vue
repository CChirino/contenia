<script setup lang="ts">
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useOnboardingPoll } from '@/composables/useOnboardingPoll';
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

// Tu primera grilla: elige mes, genera con plans.generate y muestra el
// progreso por polling a onboarding.status?month=.
const props = defineProps<{
    brand: Record<string, any> | null;
    state?: Record<string, any>;
}>();

const nextMonth = () => {
    const d = new Date();
    d.setMonth(d.getMonth() + 1);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
};

const month = ref<string>((props.state?.month as string) ?? nextMonth());
const generating = ref(false);
const status = ref<Record<string, any> | null>(null);

const statusUrl = computed(() => `/onboarding/status?month=${month.value}`);
const poll = useOnboardingPoll(statusUrl, 4000);
watch(poll.data, (d) => {
    if (!d) return;
    status.value = d;
    if (generating.value && !d.plan?.generating_at) {
        generating.value = false;
        poll.stop();
    }
});

const generate = () => {
    if (!props.brand) return;
    const [y, m] = month.value.split('-').map(Number);
    generating.value = true;
    // Guarda el mes elegido en el estado y lanza la generación real.
    router.patch('/onboarding/state', { month: month.value }, { preserveState: true });
    router.post(
        route('plans.generate', { brand: props.brand.slug, year: y, month: m }),
        {},
        {
            preserveScroll: true,
            onFinish: () => poll.start(),
            onError: () => (generating.value = false),
        },
    );
};

const postsCount = computed(() => status.value?.plan?.posts_count ?? 0);
const pending = computed(() => generating.value || status.value?.plan?.generating_at);
const lastError = computed(() => status.value?.plan?.last_error);
const done = computed(() => status.value !== null && !pending.value && postsCount.value > 0);

const complete = () => router.post('/onboarding/complete');
</script>

<template>
    <div v-if="brand">
        <h2 class="text-2xl font-bold text-fg">Tu primera grilla</h2>
        <p class="mt-2 text-fg-2">
            La IA generará los posts del mes para las redes de tu plan.
        </p>

        <div class="mt-6 space-y-5 rounded-lg bg-surface p-6 shadow-sm ring-1 ring-line">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="ob_month" value="Mes de la grilla" />
                    <input
                        id="ob_month"
                        v-model="month"
                        type="month"
                        class="mt-1 block w-full rounded-md border-line-strong bg-surface text-fg shadow-sm focus:border-brand-primary focus:ring-brand-primary"
                    />
                </div>
                <div class="text-sm text-muted">
                    <span class="font-medium text-fg">Plan {{ brand.plan?.name }}</span>:
                    {{ brand.plan?.posts_per_month ? `hasta ${brand.plan.posts_per_month} posts al mes` : 'posts ilimitados' }}.
                </div>
            </div>

            <div v-if="pending" class="space-y-2">
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-surface-3">
                    <div class="h-full w-1/3 animate-pulse rounded-full bg-brand-primary" />
                </div>
                <p class="text-sm text-muted">Generando… {{ postsCount }} posts creados</p>
            </div>
            <p v-if="lastError" class="text-sm text-red-600 dark:text-red-400">{{ lastError }}</p>

            <div v-if="done" class="rounded-md border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700 dark:border-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                ¡Listo! {{ postsCount }} posts creados en tu grilla de {{ month }}.
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton
                    v-if="!done"
                    :disabled="pending"
                    class="px-6 py-3"
                    @click="generate"
                >
                    Generar grilla
                </PrimaryButton>
                <PrimaryButton v-else class="px-6 py-3" @click="complete">
                    Ver mi grilla →
                </PrimaryButton>
                <button type="button" class="text-sm text-muted underline hover:text-fg" @click="complete">
                    Lo haré después
                </button>
            </div>
        </div>
    </div>
    <p v-else class="text-sm text-muted">Primero crea tu marca (paso 2).</p>
</template>
