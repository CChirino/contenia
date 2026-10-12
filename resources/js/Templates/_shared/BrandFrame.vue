<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { iconPaths } from './icons';

const props = withDefaults(
    defineProps<{
        width: number;
        height: number;
        /** Foto de la cabecera; null → degradado de marca */
        photoUrl?: string | null;
        /** Alto del bloque de foto en px (según plantilla) */
        photoHeight: number;
        logoUrl: string;
        brandUrl?: string | null;
        pillar?: string | null;
        icon?: string | null;
        safeTop?: number;
        safeBottom?: number;
        /** Versión reducida (slides interiores del carrusel) */
        compact?: boolean;
    }>(),
    {
        photoUrl: null,
        brandUrl: null,
        pillar: null,
        icon: 'lightbulb',
        safeTop: 0,
        safeBottom: 0,
        compact: false,
    },
);

const isVertical = computed(() => props.height > props.width);

const photoH = computed(() =>
    props.compact ? Math.round(props.photoHeight * 0.7) : props.photoHeight
);

const circleSize = computed(() =>
    props.compact ? 140 : isVertical.value ? 180 : 200
);

const iconSize = computed(() => (props.compact ? 68 : 96));

const paths = computed(() => iconPaths(props.icon));

const containerStyle = computed(() => ({
    width: `${props.width}px`,
    height: `${props.height}px`,
}));

const columnStyle = computed(() => ({
    paddingTop: `${56 + props.safeTop}px`,
    paddingBottom: `${48 + props.safeBottom}px`,
}));

const circleStyle = computed(() => ({
    top: `${photoH.value - circleSize.value / 2 + 4}px`,
    right: '64px',
    width: `${circleSize.value}px`,
    height: `${circleSize.value}px`,
    background: '#7854D2',
}));

onMounted(async () => {
    await document.fonts.ready;

    if (!props.photoUrl) {
        window.__RENDER_READY__ = true;
        return;
    }

    const img = new Image();
    img.onload = img.onerror = () => {
        window.__RENDER_READY__ = true;
    };
    img.src = props.photoUrl;
});
</script>

<template>
    <div class="relative overflow-hidden font-sans" :style="containerStyle">
        <!-- Bloque de foto (sin overlay) -->
        <img
            v-if="photoUrl"
            :src="photoUrl"
            alt=""
            class="absolute inset-x-0 top-0 w-full object-cover"
            :style="{ height: `${photoH}px` }"
        >
        <div
            v-else
            class="absolute inset-x-0 top-0"
            :style="{ height: `${photoH}px`, background: 'linear-gradient(135deg, #27C2A5, #7854D2)' }"
        />

        <!-- Costura teal + panel de marca -->
        <div
            class="absolute inset-x-0"
            :style="{ top: `${photoH}px`, height: '8px', background: '#27C2A5' }"
        />
        <div
            class="absolute inset-x-0 bottom-0"
            :style="{ top: `${photoH}px`, background: '#1A2A42' }"
        />

        <!-- Círculo púrpura con icono, mordiendo la foto -->
        <div
            class="absolute flex items-center justify-center rounded-full"
            :style="circleStyle"
        >
            <svg
                :width="iconSize"
                :height="iconSize"
                viewBox="0 0 24 24"
                fill="none"
                stroke="white"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path v-for="(d, i) in paths" :key="i" :d="d" />
            </svg>
        </div>

        <div class="relative flex h-full flex-col px-16" :style="columnStyle">
            <div
                class="flex w-fit items-center gap-4 rounded-full px-6 py-4"
                style="background: rgba(26, 42, 66, 0.75)"
            >
                <img :src="logoUrl" alt="Saberé" class="h-[72px] w-[72px]">
                <span class="text-[40px] font-semibold text-sabere-light">Saberé.</span>
            </div>

            <div class="relative flex flex-1 flex-col justify-end">
                <slot />
            </div>

            <div class="relative z-10 mt-8 flex items-center justify-between">
                <span class="flex items-center gap-6">
                    <span v-if="brandUrl" class="text-[28px] text-sabere-light/70">{{ brandUrl }}</span>
                    <slot name="footer-extra" />
                </span>
                <span
                    v-if="pillar"
                    class="rounded-full bg-sabere-purple px-5 py-2 text-[24px] font-medium text-white"
                >{{ pillar }}</span>
            </div>
        </div>
    </div>
</template>
