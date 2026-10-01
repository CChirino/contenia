<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PostDrawer from '@/Pages/Plans/PostDrawer.vue';
import { ContentPillar, MonthlyPlan, NetworkName, Post } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{
    year: number;
    month: number;
    plan: MonthlyPlan | null;
    posts: Post[];
    pillars: ContentPillar[];
    allowedFormats: Record<NetworkName, string[]>;
}>();

const monthNames = [
    'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
    'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
];

const networkLabels: Record<NetworkName, string> = {
    instagram: 'Instagram',
    linkedin: 'LinkedIn',
    tiktok: 'TikTok',
};

const networkChipClass: Record<NetworkName, string> = {
    instagram: 'bg-sabere-purple',
    linkedin: 'bg-sabere-dark',
    tiktok: 'bg-sabere-accent',
};

const statusDotClass: Record<string, string> = {
    drafted: 'bg-gray-400',
    approved: 'bg-blue-500',
    rendered: 'bg-teal-500',
    scheduled: 'bg-purple-500',
    published: 'bg-green-500',
    failed: 'bg-red-500',
};

const statusLabels: Record<string, string> = {
    drafted: 'Borrador',
    approved: 'Aprobado',
    rendered: 'Renderizado',
    scheduled: 'Programado',
    published: 'Publicado',
    failed: 'Fallido',
};

const networkFilter = ref<NetworkName | null>(null);
const selectedPostId = ref<number | null>(null);

const page = usePage();
const generateError = computed(() => (page.props.errors as Record<string, string>).generate);

const pending = computed(() => props.plan?.generating_at != null);

const daysInMonth = new Date(props.year, props.month, 0).getDate();
const firstDayOffset = (new Date(props.year, props.month - 1, 1).getDay() + 6) % 7;

const cells = computed(() => {
    const result: ({ date: string; day: number } | null)[] = [];
    for (let i = 0; i < firstDayOffset; i++) result.push(null);
    for (let day = 1; day <= daysInMonth; day++) {
        const date = `${props.year}-${String(props.month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        result.push({ date, day });
    }
    return result;
});

const filteredPosts = computed(() =>
    networkFilter.value
        ? props.posts.filter((p) => p.network === networkFilter.value)
        : props.posts
);

const postsByDate = computed(() => {
    const map: Record<string, Post[]> = {};
    for (const post of filteredPosts.value) {
        (map[post.scheduled_date] ??= []).push(post);
    }
    return map;
});

const selectedPost = computed(() =>
    props.posts.find((p) => p.id === selectedPostId.value) ?? null
);

const prevMonth = computed(() => {
    const d = new Date(props.year, props.month - 2, 1);
    return { year: d.getFullYear(), month: d.getMonth() + 1 };
});

const nextMonth = computed(() => {
    const d = new Date(props.year, props.month, 1);
    return { year: d.getFullYear(), month: d.getMonth() + 1 };
});

const navigate = (target: { year: number; month: number }) => {
    router.visit(route('plans.show', target));
};

const generate = () => {
    if (
        props.plan &&
        props.plan.posts_count > 0 &&
        !confirm('¿Regenerar la grilla del mes? Los posts en borrador serán reemplazados.')
    ) {
        return;
    }
    router.post(route('plans.generate', { year: props.year, month: props.month }));
};

let pollTimer: ReturnType<typeof setInterval> | null = null;

const stopPolling = () => {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
};

const startPolling = () => {
    stopPolling();
    pollTimer = setInterval(async () => {
        const res = await fetch(
            route('plans.status', { year: props.year, month: props.month }),
            { headers: { Accept: 'application/json' } }
        );
        const data = await res.json();
        if (!data.pending) {
            stopPolling();
            router.reload({ only: ['plan', 'posts'] });
        }
    }, 3000);
};

onMounted(() => {
    if (pending.value) startPolling();
});

onBeforeUnmount(stopPolling);
</script>

<template>
    <Head :title="`Plan ${monthNames[month - 1]} ${year}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Grilla de contenido
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Toolbar -->
                <div class="mb-4 flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50"
                            @click="navigate(prevMonth)"
                        >
                            &larr;
                        </button>
                        <h3 class="w-44 text-center text-lg font-medium capitalize text-gray-900">
                            {{ monthNames[month - 1] }} {{ year }}
                        </h3>
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50"
                            @click="navigate(nextMonth)"
                        >
                            &rarr;
                        </button>
                    </div>

                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            class="rounded-full px-3 py-1 text-xs font-medium"
                            :class="networkFilter === null
                                ? 'bg-sabere-dark text-white'
                                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            @click="networkFilter = null"
                        >
                            Todas
                        </button>
                        <button
                            v-for="(label, network) in networkLabels"
                            :key="network"
                            type="button"
                            class="rounded-full px-3 py-1 text-xs font-medium"
                            :class="networkFilter === network
                                ? `${networkChipClass[network]} text-white`
                                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            @click="networkFilter = network"
                        >
                            {{ label }}
                        </button>
                    </div>

                    <div class="ms-auto">
                        <button
                            type="button"
                            :disabled="pending"
                            class="rounded-md bg-sabere-accent px-4 py-2 text-sm font-medium text-white hover:opacity-90 disabled:opacity-50"
                            @click="generate"
                        >
                            <span v-if="pending">Generando…</span>
                            <span v-else-if="!plan || plan.posts_count === 0">Generar grilla</span>
                            <span v-else>Regenerar mes</span>
                        </button>
                    </div>
                </div>

                <div
                    v-if="plan?.last_error || generateError"
                    class="mb-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700"
                >
                    {{ plan?.last_error ?? generateError }}
                </div>

                <div
                    v-if="pending"
                    class="mb-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700"
                >
                    Generando la grilla… esta página se actualizará automáticamente.
                </div>

                <!-- Grilla mensual -->
                <div class="grid grid-cols-7 gap-px overflow-hidden rounded-lg border border-gray-200 bg-gray-200">
                    <div
                        v-for="weekday in ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom']"
                        :key="weekday"
                        class="bg-gray-50 px-2 py-1 text-center text-xs font-medium text-gray-500"
                    >
                        {{ weekday }}
                    </div>

                    <div
                        v-for="(cell, i) in cells"
                        :key="i"
                        class="min-h-28 bg-white p-1"
                        :class="{ 'bg-gray-50': cell === null }"
                    >
                        <template v-if="cell">
                            <div class="px-1 text-xs font-medium text-gray-500">
                                {{ cell.day }}
                            </div>
                            <button
                                v-for="post in postsByDate[cell.date] ?? []"
                                :key="post.id"
                                type="button"
                                class="mt-1 block w-full rounded border border-gray-100 bg-sabere-light p-1 text-left hover:border-sabere-accent"
                                @click="selectedPostId = post.id"
                            >
                                <div class="flex items-center gap-1">
                                    <span
                                        class="rounded px-1.5 py-0.5 text-[10px] font-semibold text-white"
                                        :class="networkChipClass[post.network]"
                                    >
                                        {{ networkLabels[post.network] }}
                                    </span>
                                    <span
                                        class="ms-auto inline-block h-2 w-2 rounded-full"
                                        :class="statusDotClass[post.status]"
                                        :title="statusLabels[post.status]"
                                    />
                                </div>
                                <p class="mt-1 line-clamp-2 text-[11px] leading-tight text-gray-700">
                                    {{ post.hook }}
                                </p>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <PostDrawer
            v-if="selectedPost"
            :post="selectedPost"
            :allowed-formats="allowedFormats"
            @close="selectedPostId = null"
        />
    </AuthenticatedLayout>
</template>
