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
    wordmark: string;
    palette: Record<string, string>;
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
        :photo-url="backgroundUrl"
        :photo-height="photoHeight"
        :icon="icon"
        :logo-url="logoUrl"
        :wordmark="wordmark"
        :palette="palette"
        :brand-url="brand.url"
        :pillar="post.content_pillar?.name"
        :compact="!isCover"
    >
        <template v-if="isCover">
            <div class="mb-8 h-1.5 w-16 bg-[var(--brand-accent)]" />
            <h1 class="text-[72px] font-bold leading-tight text-[color:var(--brand-light)]">{{ post.hook }}</h1>
            <p class="mt-10 text-[36px] font-medium text-[color:var(--brand-accent)]">Desliza →</p>
        </template>

        <template v-else-if="isCta">
            <div class="mb-8 h-1.5 w-16 bg-[var(--brand-accent)]" />
            <h1 class="text-[64px] font-bold leading-tight text-[color:var(--brand-light)]">{{ brand.cta ?? 'Conoce más' }}</h1>
            <p v-if="brand.url" class="mt-8 text-[44px] font-semibold text-[color:var(--brand-accent)]">{{ brand.url }}</p>
            <span class="mt-10 inline-flex w-fit rounded-full bg-[var(--brand-secondary)] px-6 py-2.5 text-[28px] font-medium text-white">
                Síguenos en {{ post.network === 'instagram' ? 'Instagram' : post.network === 'tiktok' ? 'TikTok' : 'LinkedIn' }}
            </span>
        </template>

        <template v-else>
            <div class="mb-8 h-1.5 w-16 bg-[var(--brand-accent)]" />
            <h1 class="text-[64px] font-bold leading-tight text-[color:var(--brand-light)]">{{ slide.title }}</h1>
            <p class="mt-8 text-[38px] leading-snug text-[color:color-mix(in_srgb,var(--brand-light)_85%,transparent)]">{{ slide.body }}</p>
        </template>

        <template #footer-extra>
            <span class="text-[28px] text-[color:color-mix(in_srgb,var(--brand-light)_60%,transparent)]">{{ frame }}/{{ framesTotal }}</span>
        </template>
    </BrandFrame>
</template>
