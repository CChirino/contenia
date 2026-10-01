<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { NetworkName, Post, PostFormat } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{
    post: Post;
    allowedFormats: Record<NetworkName, string[]>;
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
    ['drafted', 'approved'].includes(props.post.status)
);

const editing = ref(false);
const showRegenerate = ref(false);

const hashtagsText = ref(props.post.hashtags.join(', '));

const editForm = useForm({
    copy: props.post.copy,
    hashtags: props.post.hashtags,
    scheduled_at: scheduledInput.value,
    format: props.post.format as PostFormat,
});

const regenerateForm = useForm({
    comment: '',
});

const approve = () => {
    router.post(route('posts.approve', props.post.id), {}, {
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
        }))
        .patch(route('posts.update', props.post.id), {
            preserveScroll: true,
            onSuccess: () => {
                editing.value = false;
            },
        });
};

const submitRegenerate = () => {
    regenerateForm.post(route('posts.regenerate', props.post.id), {
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
            class="absolute inset-0 bg-gray-900/50"
            @click="emit('close')"
        />
        <div class="absolute inset-y-0 right-0 flex w-full sm:max-w-lg">
            <div class="flex h-full w-full flex-col overflow-y-auto bg-white shadow-xl">
                <div class="flex items-center justify-between border-b px-6 py-4">
                    <div class="flex items-center gap-2">
                        <span class="rounded bg-sabere-dark px-2 py-0.5 text-xs font-semibold text-white">
                            {{ networkLabels[post.network] }}
                        </span>
                        <span class="text-sm text-gray-600">
                            {{ scheduledLocal }}
                        </span>
                    </div>
                    <button
                        type="button"
                        class="rounded-md p-1 text-gray-400 hover:text-gray-600"
                        @click="emit('close')"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="flex-1 space-y-5 px-6 py-4">
                    <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-600">
                        <span><strong>Pilar:</strong> {{ post.content_pillar?.name ?? '—' }}</span>
                        <span><strong>Formato:</strong> {{ formatLabels[post.format] ?? post.format }}</span>
                        <span><strong>Estado:</strong> {{ statusLabels[post.status] ?? post.status }}</span>
                    </div>

                    <div
                        v-if="post.regeneration_error"
                        class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700"
                    >
                        {{ post.regeneration_error }}
                    </div>

                    <div
                        v-if="post.error"
                        class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700"
                    >
                        {{ post.error }}
                    </div>

                    <div>
                        <h4 class="text-xs font-semibold uppercase text-gray-500">Hook</h4>
                        <p class="mt-1 text-sm font-medium text-gray-900">{{ post.hook }}</p>
                    </div>

                    <div>
                        <h4 class="text-xs font-semibold uppercase text-gray-500">Copy</h4>
                        <p class="mt-1 whitespace-pre-line text-sm text-gray-700">{{ post.copy }}</p>
                    </div>

                    <div>
                        <h4 class="text-xs font-semibold uppercase text-gray-500">Hashtags</h4>
                        <p class="mt-1 text-sm text-sabere-purple">{{ post.hashtags.join(' ') }}</p>
                    </div>

                    <div v-if="post.design_payload?.slides?.length">
                        <h4 class="text-xs font-semibold uppercase text-gray-500">Slides</h4>
                        <ol class="mt-1 list-decimal space-y-1 ps-5 text-sm text-gray-700">
                            <li v-for="(slide, i) in post.design_payload.slides" :key="i">
                                <strong>{{ slide.title }}</strong> — {{ slide.body }}
                            </li>
                        </ol>
                    </div>

                    <div v-if="post.background_prompt">
                        <h4 class="text-xs font-semibold uppercase text-gray-500">Fondo (prompt)</h4>
                        <p class="mt-1 text-sm text-gray-700">{{ post.background_prompt }}</p>
                    </div>

                    <div class="flex h-32 items-center justify-center rounded-md bg-gray-100 text-sm text-gray-400">
                        Diseño pendiente (Fase 2)
                    </div>

                    <!-- Edición inline -->
                    <form v-if="editing" @submit.prevent="submitEdit" class="space-y-4 rounded-md border border-gray-200 p-4">
                        <div>
                            <InputLabel for="edit_copy" value="Copy" />
                            <textarea
                                id="edit_copy"
                                v-model="editForm.copy"
                                rows="5"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
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
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
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
                        <div class="flex gap-2">
                            <PrimaryButton :disabled="editForm.processing">Guardar</PrimaryButton>
                            <SecondaryButton type="button" @click="editing = false">Cancelar</SecondaryButton>
                        </div>
                    </form>

                    <!-- Regenerar -->
                    <form v-if="showRegenerate" @submit.prevent="submitRegenerate" class="space-y-3 rounded-md border border-gray-200 p-4">
                        <div>
                            <InputLabel for="regen_comment" value="Comentario (opcional)" />
                            <textarea
                                id="regen_comment"
                                v-model="regenerateForm.comment"
                                rows="3"
                                maxlength="500"
                                placeholder="Ej.: hazlo más breve, cambia el enfoque…"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <InputError :message="regenerateForm.errors.comment" class="mt-1" />
                        </div>
                        <div class="flex gap-2">
                            <PrimaryButton :disabled="regenerateForm.processing">Regenerar</PrimaryButton>
                            <SecondaryButton type="button" @click="showRegenerate = false">Cancelar</SecondaryButton>
                        </div>
                    </form>
                </div>

                <div class="flex gap-2 border-t px-6 py-4">
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
