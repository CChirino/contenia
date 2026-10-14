<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';

const props = defineProps<{
    template: { name: string; component: string; width: number; height: number; photo_height: number };
    frame: number;
    framesTotal: number;
    post: any;
    backgroundUrl?: string | null;
    icon?: string | null;
    logoUrl: string;
    wordmark: string;
    palette: Record<string, string>;
    brand: { url?: string | null; cta?: string | null };
}>();

const components: Record<string, ReturnType<typeof defineAsyncComponent>> = {
    'LinkedIn/SingleImage': defineAsyncComponent(() => import('@/Templates/LinkedIn/SingleImage.vue')),
    'Instagram/Quote': defineAsyncComponent(() => import('@/Templates/Instagram/Quote.vue')),
    'Instagram/CarouselSlide': defineAsyncComponent(() => import('@/Templates/Instagram/CarouselSlide.vue')),
    'Instagram/ReelFrame': defineAsyncComponent(() => import('@/Templates/Instagram/ReelFrame.vue')),
    'TikTok/VideoFrame': defineAsyncComponent(() => import('@/Templates/TikTok/VideoFrame.vue')),
};

const component = computed(() => components[props.template.component]);
</script>

<template>
    <component
        :is="component"
        v-if="component"
        :post="post"
        :frame="frame"
        :frames-total="framesTotal"
        :background-url="backgroundUrl"
        :photo-height="template.photo_height"
        :icon="icon"
        :logo-url="logoUrl"
        :wordmark="wordmark"
        :palette="palette"
        :brand="brand"
    />
</template>
