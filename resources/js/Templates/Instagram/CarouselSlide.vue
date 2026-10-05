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

const isCover = computed(() => props.frame === 1);
const isCta = computed(() => props.frame === props.framesTotal);
// Frame 1 = portada (hook); frames 2..N-1 = slides[1..] (slides[0] suele repetir la portada); frame N = CTA.
const slide = computed(() => slideAt(props.post, props.frame - 1));
</script>

<template>
    <BrandFrame
        :width="1080"
        :height="1350"
        :background-url="isCover ? backgroundUrl : null"
        :logo-url="logoUrl"
        :brand-url="brand.url"
        :pillar="post.content_pillar?.name"
    >
        <template v-if="isCover">
            <div class="mb-8 h-1.5 w-16 bg-sabere-accent" />
            <h1 class="text-[72px] font-bold leading-tight text-sabere-light">{{ post.hook }}</h1>
            <p class="mt-10 text-[36px] font-medium text-sabere-accent">Desliza →</p>
        </template>

        <template v-else-if="isCta">
            <div class="mb-8 h-1.5 w-16 bg-sabere-accent" />
            <h1 class="text-[64px] font-bold leading-tight text-sabere-light">{{ brand.cta ?? 'Conoce más' }}</h1>
            <p v-if="brand.url" class="mt-8 text-[40px] font-semibold text-sabere-accent">{{ brand.url }}</p>
        </template>

        <template v-else>
            <div class="mb-8 h-1.5 w-16 bg-sabere-accent" />
            <h1 class="text-[64px] font-bold leading-tight text-sabere-light">{{ slide.title }}</h1>
            <p class="mt-8 text-[38px] leading-snug text-sabere-light/85">{{ slide.body }}</p>
        </template>

        <p class="absolute bottom-24 right-16 text-[30px] font-medium text-sabere-light/60">
            {{ frame }}/{{ framesTotal }}
        </p>
    </BrandFrame>
</template>
