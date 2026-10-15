<script setup lang="ts">
import ReviewLayout from '@/Layouts/ReviewLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface ReviewPost {
    id: number;
    network: string;
    format: string;
    scheduled_date: string;
    scheduled_time: string;
    hook: string;
    copy: string;
    hashtags: string[];
    decision: 'pending' | 'approved' | 'rejected';
    comment: string | null;
    media: { type: 'image' | 'carousel' | 'video'; urls: string[] };
}

interface Week {
    week_from: string;
    week_to: string;
    comment: string | null;
    posts: ReviewPost[];
}

const props = defineProps<{
    brand: { name: string; logo_url: string; palette: Record<string, string> | null };
    review: {
        from_date: string;
        to_date: string;
        expires_at: string;
        submitted_at: string | null;
        general_comment: string | null;
        summary: { approved: number; rejected: number; pending: number };
        token: string;
    };
    weeks: Week[];
}>();

const accent = computed(() => props.brand.palette?.accent ?? '#27C2A5');
const submitted = computed(() => props.review.submitted_at !== null);

const allPosts = computed(() => props.weeks.flatMap((w) => w.posts));
const decided = computed(
    () => allPosts.value.filter((p) => p.decision !== 'pending').length
);
const progress = computed(() =>
    allPosts.value.length
        ? Math.round((decided.value / allPosts.value.length) * 100)
        : 0
);

const url = (path: string) => `/review/${props.review.token}${path}`;

const post_ = (path: string, data: Record<string, string | null> = {}) =>
    router.post(url(path), data, { preserveScroll: true });

const approve = (post: ReviewPost) =>
    post_(`/items/${post.id}`, { decision: 'approved' });

// Rechazo: abre un textarea por post; el comentario es obligatorio.
const rejecting = ref<number | null>(null);
const rejectComment = ref('');

const startReject = (post: ReviewPost) => {
    rejecting.value = post.id;
    rejectComment.value = post.comment ?? '';
};

const confirmReject = (post: ReviewPost) => {
    if (!rejectComment.value.trim()) return;
    post_(`/items/${post.id}`, {
        decision: 'rejected',
        comment: rejectComment.value,
    });
    rejecting.value = null;
};

const approveWeek = (week: Week) => post_(`/weeks/${week.week_from}/approve`);
const approveAll = () => post_('/approve-all');

// Comentario semanal: se guarda al perder el foco.
const weekDrafts = ref<Record<string, string>>({});
const weekDraft = (week: Week) =>
    weekDrafts.value[week.week_from] ?? week.comment ?? '';

const saveWeekComment = (week: Week) => {
    const comment = weekDrafts.value[week.week_from];
    if (comment === undefined || comment === (week.comment ?? '')) return;
    if (!comment.trim()) return;
    post_(`/weeks/${week.week_from}/comment`, { comment });
};

const generalComment = ref(props.review.general_comment ?? '');

const submit = () =>
    post_('/submit', { general_comment: generalComment.value || null });

const networkLabels: Record<string, string> = {
    instagram: 'Instagram',
    linkedin: 'LinkedIn',
    tiktok: 'TikTok',
};

const decisionBadge: Record<string, string> = {
    approved: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-200',
    rejected: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-200',
    pending: 'bg-surface-3 text-muted',
};

const decisionLabel: Record<string, string> = {
    approved: 'Aprobado',
    rejected: 'Rechazado',
    pending: 'Pendiente',
};

const formatDate = (iso: string) => {
    const [y, m, d] = iso.split('-');
    return `${d}/${m}/${y}`;
};

const carouselIndex = ref<Record<number, number>>({});
const carouselIdx = (post: ReviewPost) => carouselIndex.value[post.id] ?? 0;
const moveCarousel = (post: ReviewPost, dir: number) => {
    const n = post.media.urls.length;
    carouselIndex.value[post.id] = ((carouselIdx(post) + dir) % n + n) % n;
};
</script>

<template>
    <Head :title="`Revisión · ${brand.name}`" />

    <ReviewLayout :accent="accent">
        <div class="mx-auto max-w-3xl px-4 pb-40 pt-8">
            <!-- Cabecera -->
            <header class="rounded-xl bg-surface p-5 shadow-sm">
                <div class="flex items-center gap-3">
                    <img :src="brand.logo_url" :alt="brand.name" class="h-10 w-10 object-contain" />
                    <div>
                        <h1 class="text-lg font-semibold">{{ brand.name }}</h1>
                        <p class="text-sm text-muted">
                            {{ formatDate(review.from_date) }} – {{ formatDate(review.to_date) }}
                        </p>
                    </div>
                </div>

                <template v-if="!submitted">
                    <div class="mt-4">
                        <div class="flex items-center justify-between text-xs text-muted">
                            <span>{{ decided }} de {{ allPosts.length }} decididos</span>
                            <span>Vence el {{ formatDate(review.expires_at) }}</span>
                        </div>
                        <div class="mt-1 h-2 overflow-hidden rounded-full bg-surface-3">
                            <div
                                class="h-full rounded-full transition-all"
                                :style="{ width: progress + '%', backgroundColor: accent }"
                            />
                        </div>
                    </div>
                    <button
                        type="button"
                        class="mt-4 w-full rounded-lg px-4 py-2.5 text-sm font-semibold text-white sm:w-auto"
                        :style="{ backgroundColor: accent }"
                        @click="approveAll"
                    >
                        Aprobar todo
                    </button>
                </template>
            </header>

            <div
                v-if="submitted"
                class="mt-6 rounded-xl border border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/30 p-5"
            >
                <h2 class="font-semibold text-green-800 dark:text-green-200">Gracias, revisión enviada</h2>
                <p class="mt-1 text-sm text-green-700 dark:text-green-300">
                    {{ review.summary.approved }} aprobados ·
                    {{ review.summary.rejected }} rechazados ·
                    {{ review.summary.pending }} pendientes
                </p>
                <p v-if="review.general_comment" class="mt-2 text-sm italic text-green-800 dark:text-green-200">
                    «{{ review.general_comment }}»
                </p>
            </div>

            <!-- Semanas -->
            <section v-for="week in weeks" :key="week.week_from" class="mt-8">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-muted">
                        Semana {{ formatDate(week.week_from) }} – {{ formatDate(week.week_to) }}
                    </h2>
                    <button
                        v-if="!submitted"
                        type="button"
                        class="rounded-md border border-line-strong bg-surface px-3 py-1 text-xs font-medium text-fg-2 hover:bg-surface-2"
                        @click="approveWeek(week)"
                    >
                        Aprobar semana
                    </button>
                </div>

                <div class="space-y-4">
                    <article
                        v-for="post in week.posts"
                        :key="post.id"
                        class="overflow-hidden rounded-xl bg-surface shadow-sm"
                    >
                        <!-- Media -->
                        <div class="relative bg-black">
                            <video
                                v-if="post.media.type === 'video' && post.media.urls[0]"
                                :src="post.media.urls[0]"
                                controls
                                playsinline
                                class="mx-auto max-h-[70vh] w-full object-contain"
                            />
                            <template v-else-if="post.media.urls.length">
                                <img
                                    :src="post.media.type === 'carousel'
                                        ? post.media.urls[carouselIdx(post)]
                                        : post.media.urls[0]"
                                    alt=""
                                    class="mx-auto max-h-[70vh] w-full object-contain"
                                />
                                <template v-if="post.media.type === 'carousel' && post.media.urls.length > 1">
                                    <button
                                        type="button"
                                        class="absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-surface/85 px-3 py-1.5 text-lg shadow"
                                        @click="moveCarousel(post, -1)"
                                    >‹</button>
                                    <button
                                        type="button"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-surface/85 px-3 py-1.5 text-lg shadow"
                                        @click="moveCarousel(post, 1)"
                                    >›</button>
                                    <span class="absolute bottom-2 right-3 rounded bg-black/60 px-2 py-0.5 text-xs text-white">
                                        {{ carouselIdx(post) + 1 }}/{{ post.media.urls.length }}
                                    </span>
                                </template>
                            </template>
                        </div>

                        <div class="p-4">
                            <div class="flex items-center gap-2 text-xs">
                                <span class="rounded bg-sabere-dark dark:bg-surface-3 px-2 py-0.5 font-semibold text-white">
                                    {{ networkLabels[post.network] ?? post.network }}
                                </span>
                                <span class="text-muted">
                                    {{ formatDate(post.scheduled_date) }} {{ post.scheduled_time }}
                                </span>
                                <span
                                    class="ms-auto rounded-full px-2 py-0.5 font-medium"
                                    :class="decisionBadge[post.decision]"
                                >
                                    {{ decisionLabel[post.decision] }}
                                </span>
                            </div>

                            <p class="mt-3 text-sm font-medium text-fg">{{ post.hook }}</p>
                            <p class="mt-1 whitespace-pre-line text-sm text-fg-2">{{ post.copy }}</p>
                            <p v-if="post.hashtags?.length" class="mt-1 text-sm text-muted">
                                {{ post.hashtags.join(' ') }}
                            </p>

                            <p
                                v-if="post.decision === 'rejected' && post.comment"
                                class="mt-3 rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-200"
                            >
                                <strong>Tu comentario:</strong> {{ post.comment }}
                            </p>

                            <div v-if="!submitted" class="mt-4">
                                <div v-if="rejecting === post.id" class="space-y-2">
                                    <textarea
                                        v-model="rejectComment"
                                        rows="3"
                                        required
                                        placeholder="¿Qué debe cambiar? (obligatorio)"
                                        class="block w-full rounded-md border-line-strong bg-surface text-fg text-sm shadow-sm focus:border-red-400 focus:ring-red-400"
                                    />
                                    <div class="flex gap-2">
                                        <button
                                            type="button"
                                            :disabled="!rejectComment.trim()"
                                            class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                                            @click="confirmReject(post)"
                                        >
                                            Confirmar rechazo
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-md border border-line-strong px-4 py-2 text-sm text-fg-2"
                                            @click="rejecting = null"
                                        >
                                            Cancelar
                                        </button>
                                    </div>
                                </div>
                                <div v-else class="flex gap-2">
                                    <button
                                        type="button"
                                        class="flex-1 rounded-lg px-4 py-2.5 text-sm font-semibold text-white sm:flex-none"
                                        :style="{ backgroundColor: accent }"
                                        @click="approve(post)"
                                    >
                                        Aprobar
                                    </button>
                                    <button
                                        type="button"
                                        class="flex-1 rounded-lg border border-line-strong px-4 py-2.5 text-sm font-medium text-fg-2 hover:bg-surface-2 sm:flex-none"
                                        @click="startReject(post)"
                                    >
                                        Rechazar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>

                <div v-if="!submitted" class="mt-3">
                    <textarea
                        :value="weekDraft(week)"
                        rows="2"
                        placeholder="Comentario para esta semana (opcional)"
                        class="block w-full rounded-md border-line-strong bg-surface text-fg text-sm shadow-sm placeholder:text-muted focus:border-sabere-accent focus:ring-sabere-accent"
                        @input="weekDrafts[week.week_from] = ($event.target as HTMLTextAreaElement).value"
                        @blur="saveWeekComment(week)"
                    />
                </div>
            </section>

            <p v-if="submitted && !weeks.length" class="mt-8 text-center text-sm text-muted">
                Esta revisión no incluía publicaciones.
            </p>
        </div>

        <!-- Pie fijo de envío -->
        <footer
            v-if="!submitted"
            class="fixed inset-x-0 bottom-0 border-t border-line bg-surface/95 p-4 backdrop-blur"
        >
            <div class="mx-auto flex max-w-3xl flex-col gap-2 sm:flex-row sm:items-end">
                <textarea
                    v-model="generalComment"
                    rows="1"
                    placeholder="Comentario general para el equipo (opcional)"
                    class="block w-full rounded-md border-line-strong bg-surface text-fg text-sm shadow-sm placeholder:text-muted focus:border-sabere-accent focus:ring-sabere-accent sm:max-w-md"
                />
                <button
                    type="button"
                    class="w-full rounded-lg px-5 py-2.5 text-sm font-semibold text-white sm:w-auto"
                    :style="{ backgroundColor: accent }"
                    @click="submit"
                >
                    Enviar revisión
                </button>
            </div>
        </footer>
    </ReviewLayout>
</template>
