<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-surface-2 pt-6 sm:justify-center sm:pt-0"
    >
        <div>
            <Link href="/">
                <span class="text-3xl font-semibold text-brand-ink dark:text-fg">Contenia<span class="text-brand-primary">.</span></span>
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden bg-surface px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg"
        >
            <slot />
        </div>
    </div>
</template>
