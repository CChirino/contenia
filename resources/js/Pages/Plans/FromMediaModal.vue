<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import type { NetworkName } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

// Modal "Nuevo post desde archivo": el operador sube foto/video y la IA
// redacta el contenido (POST posts.from-media).
const props = defineProps<{
    year: number;
    month: number;
    networks: NetworkName[];
    takenDates: Record<string, string[]>;
}>();

const emit = defineEmits<{ close: [] }>();

const networkLabels: Record<NetworkName, string> = {
    instagram: 'Instagram',
    linkedin: 'LinkedIn',
    tiktok: 'TikTok',
};

const ACCEPT = 'image/jpeg,image/png,image/webp,video/mp4,video/quicktime';
const IMAGE_MAX = 10 * 1024 * 1024;
const VIDEO_MAX = 100 * 1024 * 1024;

const form = useForm<{
    file: File | null;
    network: NetworkName;
    mode: 'template' | 'raw';
    date: string;
    note: string;
}>({
    file: null,
    network: props.networks[0] ?? 'instagram',
    mode: 'template',
    date: '',
    note: '',
});

const previewUrl = ref<string | null>(null);
const fileName = ref('');
const fileSize = ref(0);
const isVideo = computed(() => form.file?.type.startsWith('video/') ?? false);
const dragOver = ref(false);
const clientError = ref('');

const pickFile = (file: File | null | undefined) => {
    clientError.value = '';
    if (!file) return;

    const isVid = file.type.startsWith('video/');
    if (isVid && file.size > VIDEO_MAX) {
        clientError.value = 'El video supera el máximo de 100 MB.';
        return;
    }
    if (!isVid && file.size > IMAGE_MAX) {
        clientError.value = 'La imagen supera el máximo de 10 MB.';
        return;
    }

    form.file = file;
    fileName.value = file.name;
    fileSize.value = file.size;
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = URL.createObjectURL(file);

    // Al cambiar el tipo de archivo puede quedar una red incompatible.
    if (!isAllowedNetwork(form.network)) {
        form.network = allowedNetworks.value[0] ?? props.networks[0];
    }
    suggestDate();
};

const isAllowedNetwork = (n: NetworkName) =>
    isVideo.value ? n !== 'linkedin' : n !== 'tiktok';

const allowedNetworks = computed(() =>
    props.networks.filter((n) => isAllowedNetwork(n))
);

const derivedFormat = computed(() => {
    if (!form.file) return '';
    if (isVideo.value) {
        return form.network === 'tiktok' ? 'video de TikTok' : 'reel de Instagram';
    }
    return 'imagen';
});

const monthPrefix = `${props.year}-${String(props.month).padStart(2, '0')}`;
const monthStart = `${monthPrefix}-01`;
const monthEnd = `${monthPrefix}-${String(new Date(props.year, props.month, 0).getDate()).padStart(2, '0')}`;

const today = new Date().toISOString().slice(0, 10);

// Primer día del mes (>= hoy si es el mes actual) sin post en esa red.
const suggestDate = () => {
    const taken = new Set(props.takenDates[form.network] ?? []);
    let d = monthStart < today && monthStart.slice(0, 7) === today.slice(0, 7)
        ? today
        : monthStart;
    if (monthStart < today && monthStart.slice(0, 7) !== today.slice(0, 7)) {
        d = monthStart; // meses pasados: también empieza en el día 1
    }
    while (taken.has(d) && d < monthEnd) {
        const next = new Date(`${d}T12:00:00`);
        next.setDate(next.getDate() + 1);
        d = next.toISOString().slice(0, 10);
    }
    form.date = d;
};

const onDrop = (e: DragEvent) => {
    dragOver.value = false;
    pickFile(e.dataTransfer?.files?.[0]);
};

const submit = () =>
    form.post(route('posts.from-media', { year: props.year, month: props.month }), {
        forceFormData: true,
        onSuccess: () => {
            emit('close');
            router.reload({ only: ['posts', 'plan', 'weeks', 'quota'] });
        },
    });

const fmtSize = (bytes: number) =>
    bytes > 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : `${Math.round(bytes / 1024)} KB`;

const modeCards = computed(() => isVideo.value
    ? [
        { value: 'template', title: 'Con marca', desc: 'Portada con el titular + logo en la esquina.' },
        { value: 'raw', title: 'Tal cual', desc: 'El video se publica intacto.' },
    ]
    : [
        { value: 'template', title: 'Con plantilla de marca', desc: 'Tu foto como fondo, con logo, titular y colores de la marca.' },
        { value: 'raw', title: 'Tal cual', desc: 'La foto se publica limpia; la IA solo redacta el texto.' },
    ]);
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="emit('close')">
        <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-lg bg-surface p-6 shadow-xl">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-fg">Nuevo post desde archivo</h3>
                <button type="button" class="rounded-md p-1 text-muted hover:text-fg-2" @click="emit('close')">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form class="mt-4 space-y-5" @submit.prevent="submit">
                <!-- Dropzone -->
                <div>
                    <label
                        class="flex min-h-28 cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed px-4 py-5 text-center transition"
                        :class="dragOver ? 'border-brand-primary bg-brand-tint dark:bg-brand-primary/10' : 'border-line-strong hover:border-brand-primary'"
                        @dragover.prevent="dragOver = true"
                        @dragleave.prevent="dragOver = false"
                        @drop.prevent="onDrop"
                    >
                        <input type="file" :accept="ACCEPT" class="hidden" @change="pickFile(($event.target as HTMLInputElement).files?.[0])">
                        <svg class="h-8 w-8 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 16V4m0 0l-4 4m4-4l4 4" />
                            <path d="M4 16v3a2 2 0 002 2h12a2 2 0 002-2v-3" />
                        </svg>
                        <span class="text-sm text-fg-2">
                            {{ fileName ? `${fileName} (${fmtSize(fileSize)})` : 'Arrastra una foto o video, o haz clic para elegir' }}
                        </span>
                        <span class="text-xs text-muted">Imagen ≤10 MB · Video ≤100 MB, ≤90 s</span>
                    </label>

                    <div v-if="previewUrl" class="mt-3">
                        <video v-if="isVideo" :src="previewUrl" muted controls class="max-h-56 w-full rounded-md border border-line" />
                        <img v-else :src="previewUrl" alt="" class="max-h-56 w-full rounded-md border border-line object-contain">
                    </div>

                    <p v-if="clientError" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ clientError }}</p>
                    <InputError :message="form.errors.file" class="mt-1" />
                </div>

                <!-- Red -->
                <div>
                    <InputLabel value="Red" />
                    <div class="mt-1 flex gap-2">
                        <button
                            v-for="n in networks"
                            :key="n"
                            type="button"
                            :disabled="!isAllowedNetwork(n)"
                            :title="!isAllowedNetwork(n)
                                ? (isVideo ? 'LinkedIn no admite video' : 'TikTok solo admite video')
                                : undefined"
                            class="rounded-full px-3 py-1.5 text-xs font-medium transition disabled:opacity-40"
                            :class="form.network === n ? 'bg-brand-primary text-white' : 'bg-surface-3 text-fg-2 hover:bg-line'"
                            @click="form.network = n; suggestDate()"
                        >
                            {{ networkLabels[n] }}
                        </button>
                    </div>
                    <p v-if="form.file" class="mt-1 text-xs text-muted">
                        Se publicará como {{ derivedFormat }}.
                    </p>
                    <InputError :message="form.errors.network" class="mt-1" />
                </div>

                <!-- Fecha -->
                <div>
                    <InputLabel for="fm_date" value="Fecha" />
                    <input
                        id="fm_date"
                        v-model="form.date"
                        type="date"
                        :min="monthStart"
                        :max="monthEnd"
                        required
                        class="mt-1 block w-full rounded-md border-line-strong bg-surface text-fg shadow-sm focus:border-brand-primary focus:ring-brand-primary"
                    >
                    <InputError :message="form.errors.date" class="mt-1" />
                </div>

                <!-- Modo -->
                <div>
                    <InputLabel value="Modo" />
                    <div class="mt-1 grid grid-cols-2 gap-2">
                        <button
                            v-for="m in modeCards"
                            :key="m.value"
                            type="button"
                            class="rounded-lg border p-3 text-left transition"
                            :class="form.mode === m.value
                                ? 'border-brand-primary bg-brand-tint ring-1 ring-brand-primary dark:bg-brand-primary/10'
                                : 'border-line hover:border-brand-primary/60'"
                            @click="form.mode = m.value as 'template' | 'raw'"
                        >
                            <p class="text-sm font-medium text-fg">{{ m.title }}</p>
                            <p class="mt-0.5 text-xs text-muted">{{ m.desc }}</p>
                        </button>
                    </div>
                </div>

                <!-- Nota -->
                <div>
                    <InputLabel for="fm_note" value="Nota para la IA (opcional)" />
                    <textarea
                        id="fm_note"
                        v-model="form.note"
                        rows="2"
                        maxlength="500"
                        placeholder="Ej.: nuestro equipo en la feria de Valencia, octubre"
                        class="mt-1 block w-full rounded-md border-line-strong bg-surface text-fg shadow-sm focus:border-brand-primary focus:ring-brand-primary"
                    />
                    <InputError :message="form.errors.note" class="mt-1" />
                </div>

                <InputError :message="(form.errors as Record<string, string>).quota" class="mt-1" />

                <div v-if="form.progress" class="h-1.5 w-full overflow-hidden rounded bg-surface-3">
                    <div class="h-full bg-brand-primary transition-all" :style="{ width: `${form.progress.percentage}%` }" />
                </div>

                <div class="flex justify-end gap-2">
                    <SecondaryButton type="button" @click="emit('close')">Cancelar</SecondaryButton>
                    <PrimaryButton :disabled="form.processing || !form.file">
                        {{ form.processing ? 'Subiendo…' : 'Crear post' }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </div>
</template>
