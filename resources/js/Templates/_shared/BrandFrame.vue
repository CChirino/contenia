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
    }>(),
    {
        backgroundUrl: null,
        brandUrl: null,
        pillar: null,
        safeTop: 0,
        safeBottom: 0,
    },
);

const containerStyle = computed(() => ({
    width: `${props.width}px`,
    height: `${props.height}px`,
    paddingTop: `${64 + props.safeTop}px`,
    paddingBottom: `${56 + props.safeBottom}px`,
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
        <div
            class="absolute inset-0"
            style="background: linear-gradient(160deg, rgba(26, 42, 66, 0.6) 0%, rgba(26, 42, 66, 0.92) 100%)"
        />

        <div class="relative flex h-full flex-col px-16">
            <div class="flex items-center gap-4">
                <img :src="logoUrl" alt="Saberé" class="h-[72px] w-[72px]">
                <span class="text-[40px] font-semibold text-sabere-light">Saberé.</span>
            </div>

            <div class="flex flex-1 flex-col justify-center">
                <slot />
            </div>

            <div class="flex items-center justify-between">
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
