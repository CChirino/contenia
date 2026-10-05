<script setup lang="ts">
import { computed, onMounted } from 'vue';

const props = withDefaults(
    defineProps<{
        width: number;
        height: number;
        backgroundUrl?: string | null;
        logoUrl: string;
        brandUrl?: string | null;
        pillar?: string | null;
        safeTop?: number;
        safeBottom?: number;
        /** true → overlay fuerte en todo el alto (slides interiores de carrusel) */
        fullOverlay?: boolean;
    }>(),
    {
        backgroundUrl: null,
        brandUrl: null,
        pillar: null,
        safeTop: 0,
        safeBottom: 0,
        fullOverlay: false,
    },
);

const containerStyle = computed(() => ({
    width: `${props.width}px`,
    height: `${props.height}px`,
    paddingTop: `${56 + props.safeTop}px`,
    paddingBottom: `${48 + props.safeBottom}px`,
}));

const isVertical = computed(() => props.height > props.width);

// Degradado vertical: deja ver la ilustración arriba y oscurece hacia abajo
// donde va el texto. En vertical el oscurecido fuerte empieza al 50%.
// Con fullOverlay el oscurecido es fuerte de principio a fin (carrusel).
const overlayStyle = computed(() => ({
    background: props.fullOverlay
        ? 'linear-gradient(180deg, rgba(26, 42, 66, 0.70) 0%, rgba(26, 42, 66, 0.94) 100%)'
        : isVertical.value
            ? 'linear-gradient(180deg, rgba(26, 42, 66, 0.10) 0%, rgba(26, 42, 66, 0.35) 45%, rgba(26, 42, 66, 0.60) 50%, rgba(26, 42, 66, 0.96) 100%)'
            : 'linear-gradient(180deg, rgba(26, 42, 66, 0.10) 0%, rgba(26, 42, 66, 0.35) 45%, rgba(26, 42, 66, 0.94) 100%)',
}));

onMounted(async () => {
    await document.fonts.ready;

    if (!props.backgroundUrl) {
        window.__RENDER_READY__ = true;
        return;
    }

    const img = new Image();
    img.onload = img.onerror = () => {
        window.__RENDER_READY__ = true;
    };
    img.src = props.backgroundUrl;
});
</script>

<template>
    <div
        class="relative overflow-hidden font-sans"
        :style="containerStyle"
    >
        <img
            v-if="backgroundUrl"
            :src="backgroundUrl"
            alt=""
            class="absolute inset-0 h-full w-full object-cover"
        >
        <div class="absolute inset-0" :style="overlayStyle" />
        <!-- Franja ligera tras el logo para legibilidad sobre cualquier fondo -->
        <div
            class="absolute inset-x-0 top-0"
            style="height: 160px; background: linear-gradient(180deg, rgba(26, 42, 66, 0.25) 0%, rgba(26, 42, 66, 0) 100%)"
        />

        <div class="relative flex h-full flex-col px-16">
            <div class="flex items-center gap-4">
                <img :src="logoUrl" alt="Saberé" class="h-[72px] w-[72px]">
                <span class="text-[40px] font-semibold text-sabere-light">Saberé.</span>
            </div>

            <div class="relative flex flex-1 flex-col justify-end">
                <!-- Panel de refuerzo tras el texto: mejora legibilidad sin bordes -->
                <div
                    class="absolute -inset-y-[120px] inset-x-0 pointer-events-none"
                    style="background: linear-gradient(180deg, rgba(26, 42, 66, 0) 0%, rgba(26, 42, 66, 0.85) 55%, rgba(26, 42, 66, 0.85) 100%)"
                />
                <div class="relative" style="text-shadow: 0 2px 12px rgba(26, 42, 66, 0.6)">
                    <slot />
                </div>
            </div>

            <div class="mt-8 flex items-center justify-between">
                <span v-if="brandUrl" class="text-[28px] text-sabere-light/70">{{ brandUrl }}</span>
                <span v-else />
                <span
                    v-if="pillar"
                    class="rounded-full bg-sabere-purple px-5 py-2 text-[24px] font-medium text-white"
                >{{ pillar }}</span>
            </div>
        </div>
    </div>
</template>
