<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

/**
 * Layout del asistente de primeros pasos: wordmark, barra de progreso
 * por pasos, "Saltar por ahora" y pie con Atrás / Continuar. El padre
 * controla la habilitación de Continuar (canNext) y la navegación.
 */
const props = withDefaults(
    defineProps<{
        title?: string;
        steps: string[];
        current: number;
        canNext?: boolean;
        nextLabel?: string;
        hideSkip?: boolean;
        hideFooter?: boolean;
        /** Oculta solo el botón principal (el paso trae sus propias acciones). */
        hideNext?: boolean;
    }>(),
    { title: 'Primeros pasos', canNext: true, nextLabel: 'Continuar' },
);

const emit = defineEmits<{ back: []; next: [] }>();

const skip = () => router.post('/onboarding/skip');
</script>

<template>
    <Head :title="title" />
    <div class="flex min-h-screen flex-col bg-surface-2 text-fg">
        <header class="border-b border-line bg-surface">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <span class="text-xl font-bold">
                    Contenia<span class="text-brand-primary">.</span>
                </span>
                <button
                    v-if="!hideSkip"
                    type="button"
                    class="text-sm text-muted transition hover:text-fg"
                    @click="skip"
                >
                    Saltar por ahora
                </button>
            </div>
            <div class="mx-auto max-w-5xl px-4 pb-4 sm:px-6">
                <div class="flex items-center gap-1 sm:gap-2">
                    <template v-for="(label, i) in steps" :key="i">
                        <div class="flex min-w-0 items-center gap-1 sm:gap-2">
                            <span
                                class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                                :class="
                                    i + 1 < current
                                        ? 'bg-brand-primary/20 text-brand-primary'
                                        : i + 1 === current
                                          ? 'bg-brand-primary text-white'
                                          : 'bg-surface-3 text-muted'
                                "
                            >
                                <span v-if="i + 1 < current">✓</span>
                                <span v-else>{{ i + 1 }}</span>
                            </span>
                            <span
                                class="hidden truncate text-xs lg:block"
                                :class="i + 1 === current ? 'font-medium text-fg' : 'text-muted'"
                            >
                                {{ label }}
                            </span>
                        </div>
                        <span
                            v-if="i < steps.length - 1"
                            class="h-px min-w-2 flex-1"
                            :class="i + 1 < current ? 'bg-brand-primary' : 'bg-line'"
                        />
                    </template>
                </div>
                <p class="mt-2 text-xs text-muted lg:hidden">
                    Paso {{ current }} de {{ steps.length }} ·
                    <span class="font-medium text-fg">{{ steps[current - 1] }}</span>
                </p>
            </div>
        </header>

        <main class="mx-auto w-full max-w-3xl flex-1 px-4 py-8 sm:px-6">
            <slot />
        </main>

        <footer v-if="!hideFooter" class="border-t border-line bg-surface">
            <div class="mx-auto flex max-w-3xl items-center justify-between px-4 py-4 sm:px-6">
                <SecondaryButton v-if="current > 1" @click="emit('back')">
                    ← Atrás
                </SecondaryButton>
                <span v-else />
                <slot name="actions">
                    <PrimaryButton v-if="!hideNext" :disabled="!canNext" @click="emit('next')">
                        {{ nextLabel }} →
                    </PrimaryButton>
                    <span v-else />
                </slot>
            </div>
        </footer>
    </div>
</template>
