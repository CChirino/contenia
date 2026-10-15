<script setup lang="ts">
// Layout público del portal de revisión: sin nav ni sesión de la app.
defineProps<{
    accent?: string;
}>();
</script>

<template>
    <div
        class="min-h-screen bg-gray-100 font-sans text-gray-900"
        :style="{ '--accent': accent ?? '#27C2A5' }"
    >
        <slot />
    </div>
</template>
