<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Action {
    action: string;
    used: number;
    limit: number | null;
}

interface Row {
    id: number;
    name: string;
    slug: string;
    logo_url: string;
    plan: { name: string; price_label: string | null } | null;
    status: string;
    actions: Action[];
    extras: {
        extra_posts: number;
        extra_regenerations: number;
        extra_image_refreshes: number;
    };
    cost_usd: number;
    tokens: number;
    image_calls: number;
    last_review_at: string | null;
    last_published_at: string | null;
}

const props = defineProps<{
    period: string; // YYYY-MM
    rows: Row[];
}>();

const actionLabels: Record<string, string> = {
    posts_generated: 'Posts',
    regenerations: 'Regen',
    image_refreshes: 'Fondos',
};

const usage = (row: Row, action: string) =>
    row.actions.find((a) => a.action === action);

const usageLabel = (a: Action | undefined) =>
    !a ? '—' : a.limit === null ? `${a.used}/∞` : `${a.used}/${a.limit}`;

const formatDate = (iso: string | null) =>
    iso
        ? new Date(iso).toLocaleDateString('es', { day: '2-digit', month: '2-digit' })
        : '—';

const changeMonth = (e: Event) => {
    const value = (e.target as HTMLInputElement).value;
    router.get(route('portfolio.index'), { month: value }, { preserveState: true });
};

// Modal de extensión
const extending = ref<Row | null>(null);
const extendForm = useForm({
    month: props.period,
    extra_posts: '',
    extra_regenerations: '',
    extra_image_refreshes: '',
});

const openExtend = (row: Row) => {
    extending.value = row;
    extendForm.month = props.period;
    extendForm.extra_posts = String(row.extras.extra_posts);
    extendForm.extra_regenerations = String(row.extras.extra_regenerations);
    extendForm.extra_image_refreshes = String(row.extras.extra_image_refreshes);
};

const submitExtend = () => {
    if (!extending.value) return;
    extendForm.post(route('portfolio.extend', { brand: extending.value.id }), {
        onSuccess: () => (extending.value = null),
    });
};
</script>

<template>
    <Head title="Administración · Cartera" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Administración · Cartera
                </h2>
                <input
                    type="month"
                    :value="period"
                    class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    @change="changeMonth"
                />
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Marca</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Plan</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Posts</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Regen</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Fondos</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Costo IA</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Tokens</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Imgs IA</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Últ. revisión</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Últ. publicado</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="row in rows" :key="row.id">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <img :src="row.logo_url" :alt="row.name" class="h-7 w-7 object-contain" />
                                        <span class="text-sm font-medium text-gray-900">{{ row.name }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ row.plan?.name ?? '—' }}
                                    <span v-if="row.plan?.price_label" class="text-xs text-gray-400">
                                        · {{ row.plan.price_label }}
                                    </span>
                                </td>
                                <td
                                    v-for="action in ['posts_generated', 'regenerations', 'image_refreshes']"
                                    :key="action"
                                    class="px-4 py-3 text-sm"
                                    :class="(() => {
                                        const a = usage(row, action);
                                        return a && a.limit !== null && a.used >= a.limit
                                            ? 'font-medium text-red-600'
                                            : 'text-gray-600';
                                    })()"
                                >
                                    {{ usageLabel(usage(row, action)) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">${{ row.cost_usd.toFixed(2) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ row.tokens.toLocaleString() }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ row.image_calls }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ formatDate(row.last_review_at) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ formatDate(row.last_published_at) }}</td>
                                <td class="px-4 py-3 text-right text-sm">
                                    <a
                                        :href="`/b/${row.slug}/plans/${period.slice(0, 4)}/${Number(period.slice(5))}`"
                                        class="text-indigo-600 hover:text-indigo-900"
                                    >
                                        Grilla
                                    </a>
                                    <Link
                                        :href="route('brands.edit', { brand: row.id })"
                                        class="ms-3 text-indigo-600 hover:text-indigo-900"
                                    >
                                        Editar
                                    </Link>
                                    <button
                                        type="button"
                                        class="ms-3 text-sabere-accent hover:opacity-80"
                                        @click="openExtend(row)"
                                    >
                                        Extender
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <Modal :show="extending !== null" @close="extending = null">
            <form v-if="extending" @submit.prevent="submitExtend" class="p-6">
                <h3 class="text-lg font-semibold text-gray-900">
                    Extender cuota · {{ extending.name }}
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                    Mes {{ period }} — los valores reemplazan los extras actuales.
                </p>

                <div class="mt-4 grid grid-cols-3 gap-4">
                    <div>
                        <InputLabel for="extra_posts" value="Posts extra" />
                        <TextInput
                            id="extra_posts"
                            v-model="extendForm.extra_posts"
                            type="number"
                            min="0"
                            class="mt-1 block w-full"
                        />
                    </div>
                    <div>
                        <InputLabel for="extra_regenerations" value="Regen extra" />
                        <TextInput
                            id="extra_regenerations"
                            v-model="extendForm.extra_regenerations"
                            type="number"
                            min="0"
                            class="mt-1 block w-full"
                        />
                    </div>
                    <div>
                        <InputLabel for="extra_image_refreshes" value="Fondos extra" />
                        <TextInput
                            id="extra_image_refreshes"
                            v-model="extendForm.extra_image_refreshes"
                            type="number"
                            min="0"
                            class="mt-1 block w-full"
                        />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="extending = null">
                        Cancelar
                    </SecondaryButton>
                    <PrimaryButton :disabled="extendForm.processing">
                        Guardar extensión
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
