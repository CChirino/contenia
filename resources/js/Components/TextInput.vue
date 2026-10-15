<script setup lang="ts">
import { onMounted, ref } from 'vue';

const model = defineModel<string>({ required: true });

const input = ref<HTMLInputElement | null>(null);

onMounted(() => {
    if (input.value?.hasAttribute('autofocus')) {
        input.value?.focus();
    }
});

defineExpose({ focus: () => input.value?.focus() });
</script>

<template>
    <input
        class="rounded-md border-line-strong bg-surface text-fg shadow-sm placeholder:text-muted focus:border-brand-primary focus:ring-brand-primary"
        v-model="model"
        ref="input"
    />
</template>
