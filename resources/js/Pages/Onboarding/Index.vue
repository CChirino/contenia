<script setup lang="ts">
import OnboardingLayout from '@/Layouts/OnboardingLayout.vue';
import AdminStep1 from './Admin/Step1.vue';
import AdminStep2 from './Admin/Step2.vue';
import AdminStep3 from './Admin/Step3.vue';
import AdminStep4 from './Admin/Step4.vue';
import AdminStep5 from './Admin/Step5.vue';
import AdminStep6 from './Admin/Step6.vue';
import OperatorStep1 from './Operator/Step1.vue';
import OperatorStep2 from './Operator/Step2.vue';
import OperatorStep3 from './Operator/Step3.vue';
import OperatorStep4 from './Operator/Step4.vue';
import OperatorStep5 from './Operator/Step5.vue';
import { router } from '@inertiajs/vue3';
import { computed, ref, type Component } from 'vue';

/**
 * Orquesta el wizard: la secuencia de pasos depende del track y, en el
 * recorrido A, de si la marca requiere aprobación del cliente (el paso
 * "Quién aprueba" se salta de la barra).
 */
const props = defineProps<{
    track: 'admin' | 'operator';
    step: number;
    brand?: Record<string, any> | null;
    plans?: { id: number; name: string; price_label: string; posts_per_month: number | null }[];
    state?: Record<string, any>;
    brands?: { id: number; name: string; slug: string }[] | null;
    allowed_formats?: Record<string, string[]>;
}>();

const adminComponents: Record<number, Component> = {
    1: AdminStep1,
    2: AdminStep2,
    3: AdminStep3,
    4: AdminStep4,
    5: AdminStep5,
    6: AdminStep6,
};
const operatorComponents: Record<number, Component> = {
    1: OperatorStep1,
    2: OperatorStep2,
    3: OperatorStep3,
    4: OperatorStep4,
    5: OperatorStep5,
};

const adminSeq = computed<number[]>(() =>
    props.brand?.requires_client_approval ? [1, 2, 3, 4, 5, 6] : [1, 2, 3, 4, 6],
);

const stepLabels = computed<string[]>(() => {
    if (props.track === 'admin') {
        const labels: Record<number, string> = {
            1: 'Bienvenida',
            2: 'Tu marca',
            3: 'La IA lee tu marca',
            4: 'Identidad',
            5: 'Quién aprueba',
            6: 'Tu primera grilla',
        };
        return adminSeq.value.map((n) => labels[n]);
    }
    return ['Bienvenida', 'La grilla', 'El post', 'Aprobación del cliente', 'Pautas'];
});

// Posición visible (1-based); props.step puede ser el número de componente.
const position = ref(
    props.track === 'admin'
        ? Math.max(1, adminSeq.value.indexOf(props.step) + 1 || props.step)
        : props.step || 1,
);

const componentFor = computed<Component>(() => {
    if (props.track === 'admin') {
        return adminComponents[adminSeq.value[position.value - 1] ?? 1];
    }
    return operatorComponents[position.value] ?? OperatorStep1;
});

// Los hijos pueden endurecer la habilitación de "Continuar".
const gate = ref(true);
const setGate = (v: boolean) => (gate.value = v);

const canNext = computed(() => {
    if (!gate.value) return false;
    if (props.track === 'admin' && adminSeq.value[position.value - 1] === 2) {
        return !!props.brand;
    }
    return true;
});

const isLast = computed(() => position.value >= stepLabels.value.length);

const persistStep = (pos: number) => {
    const compStep = props.track === 'admin' ? adminSeq.value[pos - 1] : pos;
    router.patch(
        '/onboarding/state',
        { track: props.track, step: compStep },
        { preserveState: true, preserveScroll: true },
    );
};

const go = (pos: number) => {
    gate.value = true;
    position.value = pos;
    persistStep(pos);
};
const next = () => go(Math.min(stepLabels.value.length, position.value + 1));
const back = () => go(Math.max(1, position.value - 1));
</script>

<template>
    <OnboardingLayout
        :steps="stepLabels"
        :current="position"
        :can-next="canNext"
        :hide-next="isLast"
        title="Primeros pasos"
        @back="back"
        @next="next"
    >
        <component
            :is="componentFor"
            :brand="brand"
            :plans="plans ?? []"
            :brands="brands ?? []"
            :state="state ?? {}"
            :allowed-formats="allowed_formats ?? {}"
            @gate="setGate"
            @next="next"
        />
    </OnboardingLayout>
</template>
