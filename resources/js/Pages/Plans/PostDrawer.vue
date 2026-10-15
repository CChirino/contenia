<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { NetworkName, Post, PostFormat, QuotaSummary } from '@/types';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useBrandRoute } from '@/composables/useBrandRoute';

const route = useBrandRoute();

const props = defineProps<{
    post: Post;
    allowedFormats: Record<NetworkName, string[]>;
    quota?: QuotaSummary | null;
}>();

const emit = defineEmits<{ close: [] }>();

const networkLabels: Record<NetworkName, string> = {
    instagram: 'Instagram',
    linkedin: 'LinkedIn',
    tiktok: 'TikTok',
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

const formatLabels: Record<string, string> = {
    image: 'Imagen',
    carousel: 'Carrusel',
    reel: 'Reel',
    video: 'Video',
};

const scheduledLocal = computed(() =>
    props.post.scheduled_at.slice(0, 16).replace('T', ' ')
);

const scheduledInput = computed(() =>
    `${props.post.scheduled_date}T${props.post.scheduled_time}`
);

const editable = computed(() =>
    ['drafted', 'approved', 'rendered', 'client_approved'].includes(props.post.status)
);

const isVideo = computed(() => ['reel', 'video'].includes(props.post.format));

const frameUrls = computed(() => props.post.asset_urls?.frames ?? []);

const currentFrame = ref(0);

const rendering = computed(() => props.post.render_requested_at !== null);

// Cuotas: null = ilimitado. El error `quota` (422) viene del servidor.
const quotaRemaining = (action: string) => {
    const a = props.quota?.actions.find((x) => x.action === action);
    if (!a || a.limit === null) return null;
    return Math.max(0, a.limit - a.used);
};

const regenExhausted = computed(() => quotaRemaining('regenerations') === 0);
const imagesExhausted = computed(() => quotaRemaining('image_refreshes') === 0);

const page = usePage();
const quotaError = computed(
    () => (page.props.errors as Record<string, string> | undefined)?.quota
);

const iconOptions = [
    'fingerprint', 'checklist', 'calendar', 'chart', 'book', 'bell',
    'chat', 'clock', 'shield', 'lightbulb', 'users', 'device',
];

const backgroundBadge = computed(() => {
    const meta = props.post.background_meta;
    switch (props.post.background_source) {
        case 'pixabay':
            return {
                label: `Foto: Pixabay · ${meta?.user ?? 'desconocido'}`,
                url: meta?.page_url ?? null,
            };
        case 'geometric':
            return { label: 'Fondo: geométrico', url: null };
        default:
            return {
                label: `Fondo: IA (${meta?.model ?? 'pollinations'})`,
                url: null,
            };
    }
});

const canGenerateDesign = computed(
    () => props.post.status === 'drafted' && !props.post.rendered_at
);

const generateDesign = () => {
    router.post(
        route('posts.render', { post: props.post.id }),
        {},
        { preserveScroll: true }
    );
};

const rerender = (freshBackground: boolean) => {
    router.post(
        route('posts.render', { post: props.post.id }),
        { fresh_background: freshBackground },
        { preserveScroll: true }
    );
};

const copied = ref(false);

const copyContent = async () => {
    await navigator.clipboard.writeText(
        `${props.post.copy}\n\n${props.post.hashtags.join(' ')}`
    );
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
};

const editing = ref(false);
const showRegenerate = ref(false);

const hashtagsText = ref(props.post.hashtags.join(', '));

const editForm = useForm({
    copy: props.post.copy,
    hashtags: props.post.hashtags,
    scheduled_at: scheduledInput.value,
    format: props.post.format as PostFormat,
    photo_keywords: props.post.photo_keywords ?? '',
    icon: props.post.icon,
});

const regenerateForm = useForm({
    comment: '',
});

const approve = () => {
    router.post(route('posts.approve', { post: props.post.id }), {}, {
        preserveScroll: true,
    });
};

const submitEdit = () => {
    editForm
        .transform((data) => ({
            ...data,
            hashtags: hashtagsText.value
                .split(',')
                .map((t) => t.trim())
                .filter((t) => t.length > 0),
            photo_keywords: data.photo_keywords || null,
        }))
        .patch(route('posts.update', { post: props.post.id }), {
            preserveScroll: true,
            onSuccess: () => {
                editing.value = false;
            },
        });
};

const submitRegenerate = () => {
    regenerateForm.post(route('posts.regenerate', { post: props.post.id }), {
        preserveScroll: true,
        onSuccess: () => {
            showRegenerate.value = false;
            regenerateForm.reset();
        },
    });
};

const onKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Escape') emit('close');
};

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <div class="fixed inset-0 z-50">
        <div
            class="absolute inset-0 bg-black/50"
            @click="emit('close')"
        />
        <div class="absolute inset-y-0 right-0 flex w-full sm:max-w-lg">
            <div class="flex h-full w-full flex-col overflow-y-auto bg-surface shadow-xl">
                <div class="flex items-center justify-between border-b border-line px-6 py-4">
                    <div class="flex items-center gap-2">
                        <span class="rounded bg-sabere-dark px-2 py-0.5 text-xs font-semibold text-white">
                            {{ networkLabels[post.network] }}
                        </span>
                        <span class="text-sm text-fg-2">
                            {{ scheduledLocal }}
                        </span>
                    </div>
                    <button
                        type="button"
                        class="rounded-md p-1 text-muted hover:text-fg-2"
                        @click="emit('close')"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="flex-1 space-y-5 px-6 py-4">
                    <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-fg-2">
                        <span><strong>Pilar:</strong> {{ post.content_pillar?.name ?? '—' }}</span>
                        <span><strong>Formato:</strong> {{ formatLabels[post.format] ?? post.format }}</span>
                        <span><strong>Estado:</strong> {{ statusLabels[post.status] ?? post.status }}</span>
                    </div>

                    <div
                        v-if="post.regeneration_error"
                        class="rounded-md border border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/30 p-3 text-sm text-red-700 dark:text-red-300"
                    >
                        {{ post.regeneration_error }}
                    </div>

                    <div
                        v-if="post.error"
                        class="rounded-md border border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/30 p-3 text-sm text-red-700 dark:text-red-300"
                    >
                        {{ post.error }}
                    </div>

                    <div
                        v-if="post.review?.comment"
                        class="rounded-md border border-amber-200 bg-amber-50 dark:border-amber-700 dark:bg-amber-900/30 p-3"
                    >
                        <h4 class="text-xs font-semibold uppercase text-amber-700 dark:text-amber-300">
                            Comentario del cliente
                            <template v-if="post.review.decision === 'rejected'">(rechazado)</template>
                        </h4>
                        <p class="mt-1 text-sm text-amber-800 dark:text-amber-200">{{ post.review.comment }}</p>
                    </div>

                    <div>
                        <h4 class="text-xs font-semibold uppercase text-muted">Hook</h4>
                        <p class="mt-1 text-sm font-medium text-fg">{{ post.hook }}</p>
                    </div>

                    <div>
                        <h4 class="text-xs font-semibold uppercase text-muted">Copy</h4>
                        <p class="mt-1 whitespace-pre-line text-sm text-fg-2">{{ post.copy }}</p>
                    </div>

                    <div>
                        <h4 class="text-xs font-semibold uppercase text-muted">Hashtags</h4>
                        <p class="mt-1 text-sm text-sabere-purple">{{ post.hashtags.join(' ') }}</p>
                    </div>

                    <div v-if="post.design_payload?.slides?.length">
                        <h4 class="text-xs font-semibold uppercase text-muted">Slides</h4>
                        <ol class="mt-1 list-decimal space-y-1 ps-5 text-sm text-fg-2">
                            <li v-for="(slide, i) in post.design_payload.slides" :key="i">
                                <strong>{{ slide.title }}</strong> — {{ slide.body }}
                            </li>
                        </ol>
                    </div>

                    <div v-if="post.background_prompt || post.photo_keywords || post.icon">
                        <h4 class="text-xs font-semibold uppercase text-muted">Fondo (prompt)</h4>
                        <p v-if="post.background_prompt" class="mt-1 text-sm text-fg-2">
                            {{ post.background_prompt }}
                        </p>
                        <p v-if="post.photo_keywords || post.icon" class="mt-1 text-sm text-muted">
                            <template v-if="post.photo_keywords">Foto: {{ post.photo_keywords }}</template>
                            <template v-if="post.photo_keywords && post.icon"> · </template>
                            <template v-if="post.icon">Icono: {{ post.icon }}</template>
                        </p>
                    </div>

                    <div>
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-semibold uppercase text-muted">Diseño</h4>
                            <a
                                v-if="post.background_source && backgroundBadge.url"
                                :href="backgroundBadge.url"
                                target="_blank"
                                rel="noopener"
                                class="rounded-full bg-surface-3 px-2 py-0.5 text-[11px] text-fg-2 hover:bg-line hover:underline"
                            >{{ backgroundBadge.label }}</a>
                            <span
                                v-else-if="post.background_source"
                                class="rounded-full bg-surface-3 px-2 py-0.5 text-[11px] text-fg-2"
                            >{{ backgroundBadge.label }}</span>
                        </div>

                        <div
                            v-if="rendering"
                            class="mt-2 flex h-32 items-center justify-center gap-2 rounded-md bg-surface-3 text-sm text-muted"
                        >
                            <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                            </svg>
                            Generando diseño…
                        </div>

                        <div
                            v-else-if="post.render_error"
                            class="mt-2 rounded-md border border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/30 p-3"
                        >
                            <p class="text-sm text-red-700 dark:text-red-300">{{ post.render_error }}</p>
                            <SecondaryButton type="button" class="mt-2" @click="rerender(false)">
                                Reintentar
                            </SecondaryButton>
                        </div>

                        <div v-else-if="frameUrls.length">
                            <!-- Carrusel con flechas -->
                            <div v-if="post.format === 'carousel'" class="mt-2">
                                <div class="relative">
                                    <img
                                        :src="frameUrls[currentFrame]"
                                        alt=""
                                        class="w-full rounded-md border border-line"
                                    >
                                    <button
                                        v-if="frameUrls.length > 1"
                                        type="button"
                                        class="absolute left-1 top-1/2 -translate-y-1/2 rounded-full bg-surface/80 px-2 py-1 text-lg shadow hover:bg-surface"
                                        @click="currentFrame = (currentFrame - 1 + frameUrls.length) % frameUrls.length"
                                    >‹</button>
                                    <button
                                        v-if="frameUrls.length > 1"
                                        type="button"
                                        class="absolute right-1 top-1/2 -translate-y-1/2 rounded-full bg-surface/80 px-2 py-1 text-lg shadow hover:bg-surface"
                                        @click="currentFrame = (currentFrame + 1) % frameUrls.length"
                                    >›</button>
                                </div>
                                <p class="mt-1 text-center text-xs text-muted">
                                    {{ currentFrame + 1 }}/{{ frameUrls.length }}
                                </p>
                            </div>

                            <!-- Reel/video: MP4 ensamblado con ffmpeg -->
                            <div v-else-if="isVideo" class="mt-2">
                                <video
                                    v-if="post.asset_urls?.final"
                                    :src="post.asset_urls.final"
                                    :poster="frameUrls[0]"
                                    controls
                                    playsinline
                                    class="w-full rounded-md border border-line"
                                />
                                <div v-else class="grid grid-cols-3 gap-1">
                                    <img
                                        v-for="(url, i) in frameUrls"
                                        :key="i"
                                        :src="url"
                                        alt=""
                                        class="rounded border border-line"
                                    >
                                </div>
                            </div>

                            <!-- Imagen única -->
                            <img
                                v-else
                                :src="post.asset_urls?.final ?? frameUrls[0]"
                                alt=""
                                class="mt-2 w-full rounded-md border border-line"
                            >
                        </div>

                        <div
                            v-else
                            class="mt-2 flex h-32 items-center justify-center rounded-md bg-surface-3 text-sm text-muted"
                        >
                            {{ canGenerateDesign ? 'Sin diseño todavía' : 'Sin diseño renderizado' }}
                        </div>

                        <div v-if="post.rendered_at" class="mt-3 flex flex-wrap gap-2">
                            <a :href="route('posts.download', { post: post.id })">
                                <SecondaryButton type="button">Descargar</SecondaryButton>
                            </a>
                            <SecondaryButton type="button" @click="rerender(false)">
                                Regenerar diseño
                            </SecondaryButton>
                            <SecondaryButton
                                type="button"
                                :disabled="imagesExhausted"
                                :title="imagesExhausted ? 'Cupo de fondos nuevos del mes agotado' : undefined"
                                @click="rerender(true)"
                            >
                                Nuevo fondo
                            </SecondaryButton>
                            <SecondaryButton type="button" @click="copyContent">
                                {{ copied ? '¡Copiado!' : 'Copiar copy + hashtags' }}
                            </SecondaryButton>
                            <p v-if="quotaError" class="w-full text-sm text-red-600 dark:text-red-400">{{ quotaError }}</p>
                        </div>
                    </div>

                    <!-- Edición inline -->
                    <form v-if="editing" @submit.prevent="submitEdit" class="space-y-4 rounded-md border border-line p-4">
                        <div>
                            <InputLabel for="edit_copy" value="Copy" />
                            <textarea
                                id="edit_copy"
                                v-model="editForm.copy"
                                rows="5"
                                class="mt-1 block w-full rounded-md border-line-strong bg-surface text-fg shadow-sm focus:border-sabere-accent focus:ring-sabere-accent"
                            />
                            <InputError :message="editForm.errors.copy" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="edit_hashtags" value="Hashtags (separados por coma)" />
                            <TextInput
                                id="edit_hashtags"
                                v-model="hashtagsText"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="editForm.errors.hashtags" class="mt-1" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="edit_scheduled" value="Fecha y hora" />
                                <TextInput
                                    id="edit_scheduled"
                                    v-model="editForm.scheduled_at"
                                    type="datetime-local"
                                    class="mt-1 block w-full"
                                />
                                <InputError :message="editForm.errors.scheduled_at" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="edit_format" value="Formato" />
                                <select
                                    id="edit_format"
                                    v-model="editForm.format"
                                    class="mt-1 block w-full rounded-md border-line-strong bg-surface text-fg shadow-sm focus:border-sabere-accent focus:ring-sabere-accent"
                                >
                                    <option
                                        v-for="f in allowedFormats[post.network]"
                                        :key="f"
                                        :value="f"
                                    >
                                        {{ formatLabels[f] ?? f }}
                                    </option>
                                </select>
                                <InputError :message="editForm.errors.format" class="mt-1" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="edit_photo_keywords" value="Foto (keywords, opcional)" />
                                <TextInput
                                    id="edit_photo_keywords"
                                    v-model="editForm.photo_keywords"
                                    type="text"
                                    maxlength="120"
                                    placeholder="school hallway lockers"
                                    class="mt-1 block w-full"
                                />
                                <InputError :message="editForm.errors.photo_keywords" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="edit_icon" value="Icono (opcional)" />
                                <select
                                    id="edit_icon"
                                    v-model="editForm.icon"
                                    class="mt-1 block w-full rounded-md border-line-strong bg-surface text-fg shadow-sm focus:border-sabere-accent focus:ring-sabere-accent"
                                >
                                    <option :value="null">—</option>
                                    <option v-for="i in iconOptions" :key="i" :value="i">{{ i }}</option>
                                </select>
                                <InputError :message="editForm.errors.icon" class="mt-1" />
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <PrimaryButton :disabled="editForm.processing">Guardar</PrimaryButton>
                            <SecondaryButton type="button" @click="editing = false">Cancelar</SecondaryButton>
                        </div>
                    </form>

                    <!-- Regenerar -->
                    <form v-if="showRegenerate" @submit.prevent="submitRegenerate" class="space-y-3 rounded-md border border-line p-4">
                        <div>
                            <InputLabel for="regen_comment" value="Comentario (opcional)" />
                            <textarea
                                id="regen_comment"
                                v-model="regenerateForm.comment"
                                rows="3"
                                maxlength="500"
                                placeholder="Ej.: hazlo más breve, cambia el enfoque…"
                                class="mt-1 block w-full rounded-md border-line-strong bg-surface text-fg shadow-sm focus:border-sabere-accent focus:ring-sabere-accent"
                            />
                            <InputError :message="regenerateForm.errors.comment" class="mt-1" />
                        </div>
                        <p v-if="quotaError" class="text-sm text-red-600 dark:text-red-400">{{ quotaError }}</p>
                        <div class="flex gap-2">
                            <PrimaryButton
                                :disabled="regenerateForm.processing || regenExhausted"
                                :title="regenExhausted ? 'Cupo de regeneraciones del mes agotado' : undefined"
                            >
                                Regenerar
                            </PrimaryButton>
                            <SecondaryButton type="button" @click="showRegenerate = false">Cancelar</SecondaryButton>
                        </div>
                    </form>
                </div>

                <div class="flex gap-2 border-t border-line px-6 py-4">
                    <PrimaryButton
                        v-if="post.status === 'drafted'"
                        type="button"
                        @click="approve"
                    >
                        Aprobar
                    </PrimaryButton>
                    <SecondaryButton
                        v-if="editable && !editing"
                        type="button"
                        @click="editing = true; showRegenerate = false"
                    >
                        Editar
                    </SecondaryButton>
                    <SecondaryButton
                        v-if="canGenerateDesign"
                        type="button"
                        @click="generateDesign"
                    >
                        Generar diseño
                    </SecondaryButton>
                    <SecondaryButton
                        v-if="post.status === 'drafted' && !showRegenerate"
                        type="button"
                        @click="showRegenerate = true; editing = false"
                    >
                        Regenerar
                    </SecondaryButton>
                </div>
            </div>
        </div>
    </div>
</template>
