<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import GenerationErrors from '@/Pages/Plans/GenerationErrors.vue';
import PostDrawer from '@/Pages/Plans/PostDrawer.vue';
import { ContentPillar, MonthlyPlan, NetworkName, Post } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

interface Week {
    from: string;
    to: string;
    posts_count: number;
}

const props = defineProps<{
    year: number;
    month: number;
    plan: MonthlyPlan | null;
    posts: Post[];
    pillars: ContentPillar[];
    allowedFormats: Record<NetworkName, string[]>;
    weeks: Week[];
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

const hasEmptyWeeks = computed(() => props.weeks.some((w) => w.posts_count === 0));

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

const rows = computed(() => {
    const result: ({ date: string; day: number } | null)[][] = [];
    for (let i = 0; i < cells.value.length; i += 7) {
        result.push(cells.value.slice(i, i + 7));
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
    router.post(route('plans.generate', { year: props.year, month: props.month }));
};

const regenerateWeek = (week: Week) => {
    if (
        !confirm(
            'Se reemplazarán los borradores de esta semana; los posts aprobados se conservan.'
        )
    ) {
        return;
    }
    router.post(
        route('plans.weeks.regenerate', {
            year: props.year,
            month: props.month,
            from: week.from,
        })
    );
};

let pollTimer: ReturnType<typeof setInterval> | null = null;

const stopPolling = () => {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
};

let lastRenderingIds = '';

const startPolling = () => {
    stopPolling();
    pollTimer = setInterval(async () => {
        const res = await fetch(
            route('plans.status', { year: props.year, month: props.month }),
            { headers: { Accept: 'application/json' } }
        );
        const data = await res.json();
        const ids = (data.rendering_ids ?? []).join(',');

        if (!data.pending && ids === '') {
            stopPolling();
            router.reload({ only: ['plan', 'posts', 'weeks'] });
            return;
        }

        if (ids !== lastRenderingIds) {
            lastRenderingIds = ids;
            router.reload({ only: ['posts'] });
        }
    }, 3000);
};

const onVisibilityChange = () => {
    if (document.visibilityState === 'visible') {
        router.reload({ only: ['plan', 'posts', 'weeks'] });
    }
};

const hasRendering = computed(() =>
    props.posts.some((p) => p.status === 'approved' && !p.rendered_at)
);

// Arranca el polling tanto al montar como cuando una acción (generar,
// regenerar semana, aprobar) deja el plan o algún post en proceso.
watch(
    () => pending.value || hasRendering.value,
    (active) => {
        if (active && !pollTimer) startPolling();
    },
    { immediate: true }
);

onMounted(() => {
    document.addEventListener('visibilitychange', onVisibilityChange);
});

onBeforeUnmount(() => {
    stopPolling();
    document.removeEventListener('visibilitychange', onVisibilityChange);
});
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
                            v-if="pending || !plan || plan.posts_count === 0 || hasEmptyWeeks"
                            type="button"
                            :disabled="pending"
                            class="rounded-md bg-sabere-accent px-4 py-2 text-sm font-medium text-white hover:opacity-90 disabled:opacity-50"
                            @click="generate"
                        >
                            <span v-if="pending">Generando…</span>
                            <span v-else-if="!plan || plan.posts_count === 0">Generar grilla</span>
                            <span v-else>Completar mes</span>
                        </button>
                    </div>
                </div>

                <GenerationErrors
                    v-if="plan?.last_error?.length"
                    :year="year"
                    :month="month"
                    :errors="plan.last_error"
                />

                <div
                    v-if="generateError"
                    class="mb-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700"
                >
                    {{ generateError }}
                    <Link
                        :href="route('settings.pillars.index')"
                        class="font-medium underline hover:text-amber-900"
                    >
                        Revisar pilares de contenido
                    </Link>
                </div>

                <div
                    v-if="pending"
                    class="mb-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700"
                >
                    Generando la grilla… esta página se actualizará automáticamente.
                </div>

                <!-- Grilla mensual -->
                <div class="grid grid-cols-[2.5rem_repeat(7,minmax(0,1fr))] gap-px overflow-hidden rounded-lg border border-gray-200 bg-gray-200">
                    <div class="bg-gray-50" />
                    <div
                        v-for="weekday in ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom']"
                        :key="weekday"
                        class="bg-gray-50 px-2 py-1 text-center text-xs font-medium text-gray-500"
                    >
                        {{ weekday }}
                    </div>

                    <template v-for="(row, weekIndex) in rows" :key="weekIndex">
                        <div class="flex items-center justify-center bg-gray-50">
                            <button
                                v-if="weeks[weekIndex]"
                                type="button"
                                :disabled="pending"
                                :title="`Regenerar semana ${weeks[weekIndex].from} – ${weeks[weekIndex].to}`"
                                class="rounded p-1 text-gray-400 hover:bg-gray-200 hover:text-sabere-dark disabled:opacity-40"
                                @click="regenerateWeek(weeks[weekIndex])"
                            >
                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.451a.75.75 0 000-1.5H4.5a.75.75 0 00-.75.75v3.75a.75.75 0 001.5 0v-2.033l.364.363a7 7 0 0011.712-3.138.75.75 0 00-1.449-.347zm-10.624-2.85a5.5 5.5 0 019.201-2.465l.312.31H11.75a.75.75 0 000 1.5h3.75a.75.75 0 00.75-.75V3.875a.75.75 0 00-1.5 0v2.033l-.364-.364A7 7 0 002.674 8.682a.75.75 0 001.449.347l.001-.002v.001-.001z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                        <div
                            v-for="(cell, i) in row"
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
                                        <img
                                            v-if="post.status === 'rendered' && post.asset_urls?.frames?.[0]"
                                            :src="post.asset_urls.frames[0]"
                                            alt=""
                                            class="h-10 w-10 shrink-0 rounded object-cover"
                                        >
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
                    </template>
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
