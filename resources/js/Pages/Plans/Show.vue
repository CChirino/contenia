<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import GenerationErrors from '@/Pages/Plans/GenerationErrors.vue';
import PostDrawer from '@/Pages/Plans/PostDrawer.vue';
import { ContentPillar, MonthlyPlan, NetworkName, Post } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useBrandRoute } from '@/composables/useBrandRoute';

const route = useBrandRoute();

interface Week {
    from: string;
    to: string;
    posts_count: number;
}

interface Review {
    id: number;
    from_date: string;
    to_date: string;
    status: 'open' | 'submitted' | 'revoked' | 'expired';
    created_at: string;
    expires_at: string;
    submitted_at: string | null;
    general_comment: string | null;
    summary: { approved: number; rejected: number; pending: number };
    week_comments: { week_from: string; comment: string }[];
}

const props = defineProps<{
    year: number;
    month: number;
    plan: MonthlyPlan | null;
    posts: Post[];
    pillars: ContentPillar[];
    allowedFormats: Record<NetworkName, string[]>;
    weeks: Week[];
    reviews: Review[];
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
    client_approved: 'bg-green-500',
    scheduled: 'bg-purple-500',
    published: 'bg-green-600',
    failed: 'bg-red-500',
};

const statusLabels: Record<string, string> = {
    drafted: 'Borrador',
    approved: 'Aprobado',
    rendered: 'Renderizado',
    client_approved: 'Aprobado por el cliente',
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

// Aprobación del cliente: visible solo si la marca lo requiere.
const requiresApproval = computed(
    () => (page.props.brand as { requires_client_approval?: boolean } | null)
        ?.requires_client_approval ?? false
);

const eligibleRendered = computed(() =>
    props.posts.filter((p) => p.status === 'rendered')
);

const weekEligible = (week: Week) =>
    props.posts.some(
        (p) =>
            p.status === 'rendered' &&
            p.scheduled_date >= week.from &&
            p.scheduled_date <= week.to
    );

const sendToReview = (week?: Week) => {
    router.post(
        route('reviews.store', { year: props.year, month: props.month }),
        week ? { week_from: week.from } : {}
    );
};

const rotateReview = (review: Review) => {
    router.post(route('reviews.rotate', { review: review.id }));
};

const revokeReview = (review: Review) => {
    if (!confirm('¿Revocar el enlace de revisión? El cliente dejará de poder acceder.')) {
        return;
    }
    router.post(route('reviews.revoke', { review: review.id }));
};

// El enlace con el token en claro llega una sola vez vía flash.
const reviewUrl = ref<string | null>(null);
watch(
    () => (page.props.flash as { review_url?: string | null }).review_url,
    (url) => {
        if (url) reviewUrl.value = url;
    },
    { immediate: true }
);

const copied = ref(false);
const copyLink = async () => {
    if (!reviewUrl.value) return;
    await navigator.clipboard.writeText(reviewUrl.value);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
};

const formatDate = (iso: string) => {
    const [y, m, d] = iso.slice(0, 10).split('-');
    return `${d}/${m}${iso.length > 10 ? '/' + y.slice(2) : ''}`;
};

const formatDateTime = (iso: string | null) =>
    iso ? new Date(iso).toLocaleDateString('es', { day: '2-digit', month: '2-digit' }) : '';

const reviewStatusText = (review: Review) => {
    const s = review.summary;
    const counts = `${s.approved} aprobados · ${s.rejected} rechazados · ${s.pending} pendientes`;
    if (review.status === 'open') {
        return `Revisión enviada el ${formatDateTime(review.created_at)} · ${counts} · vence el ${formatDate(review.expires_at)}`;
    }
    if (review.status === 'submitted') {
        return `Enviada el ${formatDateTime(review.submitted_at)} · ${counts}`;
    }
    return review.status === 'revoked'
        ? `Revisión revocada · ${counts}`
        : `Revisión vencida · ${counts}`;
};

const weekComments = computed(() => {
    const map: Record<string, string> = {};
    for (const review of props.reviews) {
        for (const c of review.week_comments) {
            map[c.week_from] ??= c.comment;
        }
    }
    return map;
});

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
    props.posts.some((p) => p.render_requested_at !== null)
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

                    <div class="ms-auto flex items-center gap-2">
                        <button
                            v-if="requiresApproval"
                            type="button"
                            :disabled="eligibleRendered.length === 0"
                            :title="eligibleRendered.length === 0 ? 'No hay diseños listos para revisar' : 'Enviar el mes a aprobación del cliente'"
                            class="rounded-md border border-sabere-accent px-4 py-2 text-sm font-medium text-sabere-accent hover:bg-sabere-accent/10 disabled:opacity-50"
                            @click="sendToReview()"
                        >
                            Enviar a aprobación
                        </button>
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

                <!-- Bandas de estado de revisiones del cliente -->
                <div
                    v-for="review in reviews"
                    :key="review.id"
                    class="mb-3 rounded-md border p-3 text-sm"
                    :class="review.status === 'open'
                        ? 'border-teal-200 bg-teal-50 text-teal-800'
                        : 'border-gray-200 bg-gray-50 text-gray-600'"
                >
                    <div class="flex flex-wrap items-center gap-3">
                        <span>{{ reviewStatusText(review) }}</span>
                        <span class="text-xs text-gray-500">
                            ({{ formatDate(review.from_date) }} – {{ formatDate(review.to_date) }})
                        </span>
                        <div v-if="review.status === 'open'" class="ms-auto flex gap-2">
                            <button
                                type="button"
                                class="rounded border border-teal-300 px-2 py-0.5 text-xs hover:bg-teal-100"
                                @click="rotateReview(review)"
                            >
                                Nuevo enlace
                            </button>
                            <button
                                type="button"
                                class="rounded border border-red-200 px-2 py-0.5 text-xs text-red-600 hover:bg-red-50"
                                @click="revokeReview(review)"
                            >
                                Revocar
                            </button>
                        </div>
                    </div>
                    <p v-if="review.general_comment" class="mt-1 text-xs italic text-gray-600">
                        «{{ review.general_comment }}»
                    </p>
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
                        <div class="flex flex-col items-center justify-center gap-0.5 bg-gray-50">
                            <button
                                v-if="weeks[weekIndex] && requiresApproval"
                                type="button"
                                :disabled="!weekEligible(weeks[weekIndex])"
                                :title="weekEligible(weeks[weekIndex])
                                    ? `Enviar semana ${weeks[weekIndex].from} – ${weeks[weekIndex].to} a aprobación`
                                    : 'Sin diseños listos para revisar esta semana'"
                                class="rounded p-1 text-gray-400 hover:bg-gray-200 hover:text-sabere-accent disabled:opacity-40"
                                @click="sendToReview(weeks[weekIndex])"
                            >
                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z" />
                                    <path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 010-1.186A10.004 10.004 0 0110 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0110 17c-4.257 0-7.893-2.66-9.336-6.41zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                </svg>
                            </button>
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
                                    class="mt-1 block w-full rounded border bg-sabere-light p-1 text-left hover:border-sabere-accent"
                                    :class="post.status === 'drafted' && post.review?.decision === 'rejected'
                                        ? 'border-red-300'
                                        : 'border-gray-100'"
                                    @click="selectedPostId = post.id"
                                >
                                    <div class="flex items-center gap-1">
                                        <img
                                            v-if="post.rendered_at && post.asset_urls?.frames?.[0]"
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
                                    <p
                                        v-if="post.status === 'drafted' && post.review?.decision === 'rejected'"
                                        class="mt-0.5 text-[10px] font-medium text-red-600"
                                    >
                                        Rechazado por el cliente
                                    </p>
                                </button>
                            </template>
                        </div>
                        <div
                            v-if="weeks[weekIndex] && weekComments[weeks[weekIndex].from]"
                            class="col-span-8 bg-amber-50 px-3 py-1.5 text-xs italic text-amber-800"
                        >
                            Comentario del cliente (semana del {{ formatDate(weeks[weekIndex].from) }}):
                            «{{ weekComments[weeks[weekIndex].from] }}»
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Modal con el enlace de revisión (se muestra una sola vez) -->
        <div
            v-if="reviewUrl"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @click.self="reviewUrl = null"
        >
            <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-gray-900">Enlace de revisión</h3>
                <p class="mt-2 break-all rounded bg-gray-50 p-3 font-mono text-xs text-gray-700">
                    {{ reviewUrl }}
                </p>
                <p class="mt-2 text-sm text-gray-500">
                    El cliente también lo recibió por correo. Este enlace no se vuelve
                    a mostrar; puedes generar uno nuevo.
                </p>
                <div class="mt-4 flex justify-end gap-3">
                    <button
                        type="button"
                        class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50"
                        @click="reviewUrl = null"
                    >
                        Cerrar
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-sabere-accent px-4 py-2 text-sm font-medium text-white hover:opacity-90"
                        @click="copyLink"
                    >
                        {{ copied ? 'Copiado ✓' : 'Copiar enlace' }}
                    </button>
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
