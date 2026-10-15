<script setup lang="ts">
// Layout público del portal de revisión: sin nav ni sesión de la app.
defineProps<{
    accent?: string;
}>();
</script>

<template>
    <div
        class="min-h-screen bg-surface-2 font-sans text-fg"
        :style="{ '--accent': accent ?? '#27C2A5' }"
    >
        <slot />
    </div>
</template>
