<script setup lang="ts">
import { computed } from 'vue';
import BrandFrame from '../_shared/BrandFrame.vue';
import { slideAt } from '../_shared/slide';

const props = defineProps<{
    post: any;
    frame: number;
    framesTotal: number;
    backgroundUrl?: string | null;
    logoUrl: string;
    brand: { url?: string | null; cta?: string | null };
}>();

const slide = computed(() => slideAt(props.post, props.frame - 1));
</script>

<template>
    <BrandFrame
        :width="1080"
        :height="1920"
        :background-url="backgroundUrl"
        :logo-url="logoUrl"
        :brand-url="brand.url"
        :pillar="post.content_pillar?.name"
        :safe-top="220"
        :safe-bottom="320"
    >
        <div class="mb-8 h-1.5 w-16 bg-sabere-accent" />
        <h1 class="text-[72px] font-bold leading-tight text-sabere-light">{{ slide.title }}</h1>
        <p class="mt-8 text-[40px] leading-snug text-sabere-light/85">{{ slide.body }}</p>

        <p class="absolute right-16 text-[30px] font-medium text-sabere-light/60" :style="{ bottom: `${376}px` }">
            {{ frame }}/{{ framesTotal }}
        </p>
    </BrandFrame>
</template>
