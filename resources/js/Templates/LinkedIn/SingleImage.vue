<script setup lang="ts">
import { computed } from 'vue';
import BrandFrame from '../_shared/BrandFrame.vue';
import { slideAt } from '../_shared/slide';

const props = defineProps<{
    post: any;
    frame: number;
    framesTotal: number;
    backgroundUrl?: string | null;
    photoHeight: number;
    icon?: string | null;
    logoUrl: string;
    brand: { url?: string | null; cta?: string | null };
}>();

const slide = computed(() => slideAt(props.post, 0));
</script>

<template>
    <BrandFrame
        :width="1200"
        :height="1200"
        :photo-url="backgroundUrl"
        :photo-height="photoHeight"
        :icon="icon"
        :logo-url="logoUrl"
        :brand-url="brand.url"
        :pillar="post.content_pillar?.name"
    >
        <div class="mb-8 h-1.5 w-16 bg-sabere-accent" />
        <h1 class="text-[64px] font-bold leading-tight text-sabere-light">{{ slide.title }}</h1>
        <p class="mt-8 text-[38px] leading-snug text-sabere-light/85">{{ slide.body }}</p>
    </BrandFrame>
</template>
