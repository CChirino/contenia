<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useBrandRoute } from '@/composables/useBrandRoute';

const route = useBrandRoute();

interface Guideline {
    id: number;
    text: string;
    source: 'ai_distilled' | 'client_feedback' | 'operator';
    status: 'pending' | 'active' | 'pending_retire' | 'retired' | 'discarded';
    weight: number;
    evidence_count: number;
}

const props = defineProps<{
    guidelines: Guideline[];
}>();

const sourceLabels: Record<Guideline['source'], string> = {
    ai_distilled: 'IA',
    client_feedback: 'Cliente',
    operator: 'Operador',
};

const statusLabels: Record<Guideline['status'], string> = {
    pending: 'Pendiente',
    active: 'Activa',
    pending_retire: 'Retiro propuesto',
    retired: 'Retirada',
    discarded: 'Descartada',
};

const byStatus = (status: Guideline['status'][]) =>
    props.guidelines.filter((g) => status.includes(g.status));

const pending = computed(() => byStatus(['pending']));
const pendingRetire = computed(() => byStatus(['pending_retire']));
const active = computed(() => byStatus(['active']));
const archived = computed(() => byStatus(['retired', 'discarded']));

// --- Nueva pauta (operador → activa directo) ---------------------------------

const createForm = useForm({ text: '' });

const submitCreate = () => {
    createForm.post(route('settings.guidelines.store'), {
        preserveScroll: true,
        onSuccess: () => createForm.reset(),
    });
};

// --- Edición inline -----------------------------------------------------------

const editingId = ref<number | null>(null);
const editForm = useForm({ text: '' });

const startEdit = (guideline: Guideline) => {
    editingId.value = guideline.id;
    editForm.text = guideline.text;
    editForm.clearErrors();
};

const cancelEdit = () => {
    editingId.value = null;
    editForm.reset();
};

const submitEdit = (guideline: Guideline) => {
    editForm.put(route('settings.guidelines.update', { guideline: guideline.id }), {
        preserveScroll: true,
        onSuccess: () => cancelEdit(),
    });
};

// --- Acciones -----------------------------------------------------------------

const post = (name: string, guideline: Guideline) => {
    router.post(
        route(name, { guideline: guideline.id }),
        {},
        { preserveScroll: true }
    );
};

const destroy = (guideline: Guideline) => {
    if (confirm('¿Eliminar esta pauta definitivamente?')) {
        router.delete(route('settings.guidelines.destroy', { guideline: guideline.id }), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head title="Pautas" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-fg">
                Configuración · Pautas
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                <p class="text-sm text-muted">
                    Pautas que la IA propone a partir del feedback; las activas se
                    inyectan en las generaciones.
                </p>

                <!-- Nueva pauta -->
                <div class="bg-surface p-4 shadow sm:rounded-lg sm:p-8">
                    <h3 class="text-lg font-medium text-fg">Añadir pauta</h3>
                    <form class="mt-3 space-y-3" @submit.prevent="submitCreate">
                        <textarea
                            v-model="createForm.text"
                            rows="2"
                            maxlength="200"
                            placeholder="Ej.: Evita preguntas retóricas en el hook; prefiera datos concretos."
                            class="block w-full rounded-md border-line-strong bg-surface text-fg shadow-sm focus:border-brand-primary focus:ring-brand-primary"
                        ></textarea>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-muted">
                                {{ createForm.text.length }} / 200 — las pautas añadidas a mano entran activas.
                            </span>
                            <PrimaryButton :disabled="createForm.processing || !createForm.text.trim()">
                                + Añadir pauta
                            </PrimaryButton>
                        </div>
                        <InputError :message="createForm.errors.text" />
                    </form>
                </div>

                <!-- Pendientes de revisar -->
                <div v-if="pending.length" class="bg-surface p-4 shadow sm:rounded-lg sm:p-8">
                    <h3 class="text-lg font-medium text-fg">Pendientes de revisar</h3>
                    <ul class="mt-3 divide-y divide-line">
                        <li v-for="g in pending" :key="g.id" class="py-3">
                            <div class="flex items-start gap-3">
                                <div class="min-w-0 flex-1">
                                    <template v-if="editingId === g.id">
                                        <TextInput v-model="editForm.text" class="w-full" maxlength="200" />
                                        <InputError :message="editForm.errors.text" class="mt-1" />
                                    </template>
                                    <p v-else class="text-sm text-fg">{{ g.text }}</p>
                                    <div class="mt-1 flex items-center gap-2 text-xs text-muted">
                                        <span class="rounded-full bg-surface-3 px-2 py-0.5">
                                            {{ sourceLabels[g.source] }}
                                        </span>
                                        <span>{{ g.evidence_count }} evidencia(s)</span>
                                        <span>peso {{ g.weight }}</span>
                                    </div>
                                </div>
                                <div class="flex shrink-0 flex-wrap gap-2">
                                    <template v-if="editingId === g.id">
                                        <PrimaryButton :disabled="editForm.processing" @click="submitEdit(g)">
                                            Guardar
                                        </PrimaryButton>
                                        <SecondaryButton @click="cancelEdit">Cancelar</SecondaryButton>
                                    </template>
                                    <template v-else>
                                        <SecondaryButton @click="post('settings.guidelines.activate', g)">
                                            ✓ Activar
                                        </SecondaryButton>
                                        <SecondaryButton @click="post('settings.guidelines.discard', g)">
                                            ✗ Descartar
                                        </SecondaryButton>
                                        <SecondaryButton @click="startEdit(g)">Editar</SecondaryButton>
                                        <DangerButton @click="destroy(g)">Eliminar</DangerButton>
                                    </template>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Retiros propuestos por la IA -->
                <div v-if="pendingRetire.length" class="bg-surface p-4 shadow sm:rounded-lg sm:p-8">
                    <h3 class="text-lg font-medium text-fg">Retiros propuestos</h3>
                    <ul class="mt-3 divide-y divide-line">
                        <li v-for="g in pendingRetire" :key="g.id" class="py-3">
                            <div class="flex items-start gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm text-fg">{{ g.text }}</p>
                                    <div class="mt-1 flex items-center gap-2 text-xs text-muted">
                                        <span class="rounded-full bg-surface-3 px-2 py-0.5">
                                            {{ sourceLabels[g.source] }}
                                        </span>
                                        <span>{{ g.evidence_count }} evidencia(s)</span>
                                    </div>
                                </div>
                                <div class="flex shrink-0 gap-2">
                                    <SecondaryButton @click="post('settings.guidelines.keep', g)">
                                        Mantener
                                    </SecondaryButton>
                                    <DangerButton @click="post('settings.guidelines.retire', g)">
                                        Retirar
                                    </DangerButton>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Activas -->
                <div class="bg-surface p-4 shadow sm:rounded-lg sm:p-8">
                    <h3 class="text-lg font-medium text-fg">Pautas activas</h3>
                    <p v-if="!active.length" class="mt-2 text-sm text-muted">
                        Aún no hay pautas activas: se inyectarán en los prompts en cuanto las haya.
                    </p>
                    <ul v-else class="mt-3 divide-y divide-line">
                        <li v-for="g in active" :key="g.id" class="py-3">
                            <div class="flex items-start gap-3">
                                <div class="min-w-0 flex-1">
                                    <template v-if="editingId === g.id">
                                        <TextInput v-model="editForm.text" class="w-full" maxlength="200" />
                                        <InputError :message="editForm.errors.text" class="mt-1" />
                                    </template>
                                    <p v-else class="text-sm text-fg">{{ g.text }}</p>
                                    <div class="mt-1 flex items-center gap-2 text-xs text-muted">
                                        <span class="rounded-full bg-surface-3 px-2 py-0.5">
                                            {{ sourceLabels[g.source] }}
                                        </span>
                                        <span>{{ g.evidence_count }} evidencia(s)</span>
                                        <span>peso {{ g.weight }}</span>
                                    </div>
                                </div>
                                <div class="flex shrink-0 gap-2">
                                    <template v-if="editingId === g.id">
                                        <PrimaryButton :disabled="editForm.processing" @click="submitEdit(g)">
                                            Guardar
                                        </PrimaryButton>
                                        <SecondaryButton @click="cancelEdit">Cancelar</SecondaryButton>
                                    </template>
                                    <template v-else>
                                        <SecondaryButton @click="startEdit(g)">Editar</SecondaryButton>
                                        <DangerButton @click="post('settings.guidelines.retire', g)">
                                            Retirar
                                        </DangerButton>
                                    </template>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Historial -->
                <div v-if="archived.length" class="bg-surface p-4 shadow sm:rounded-lg sm:p-8">
                    <h3 class="text-lg font-medium text-fg">Historial</h3>
                    <ul class="mt-3 divide-y divide-line">
                        <li v-for="g in archived" :key="g.id" class="py-3">
                            <div class="flex items-start gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm text-muted line-through decoration-line-strong/60">
                                        {{ g.text }}
                                    </p>
                                    <div class="mt-1 flex items-center gap-2 text-xs text-muted">
                                        <span class="rounded-full bg-surface-3 px-2 py-0.5">
                                            {{ sourceLabels[g.source] }}
                                        </span>
                                        <span>{{ statusLabels[g.status] }}</span>
                                    </div>
                                </div>
                                <DangerButton
                                    v-if="g.status === 'discarded'"
                                    class="shrink-0"
                                    @click="destroy(g)"
                                >
                                    Eliminar
                                </DangerButton>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
