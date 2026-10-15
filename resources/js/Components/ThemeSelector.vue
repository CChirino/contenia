<script setup lang="ts">
import { useTheme, type Theme } from '@/composables/useTheme';

const { theme, setTheme } = useTheme();

const options: { value: Theme; label: string; icon: string }[] = [
    {
        value: 'light',
        label: 'Claro',
        icon: 'M12 3v1m0 16v1m8.66-9h-1M4.34 12h-1m14.14-6.36-.7.7M6.34 17.66l-.7.7m12.02.7-.7-.7M6.34 6.34l-.7-.7M12 8a4 4 0 100 8 4 4 0 000-8z',
    },
    {
        value: 'dark',
        label: 'Oscuro',
        icon: 'M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z',
    },
    {
        value: 'system',
        label: 'Sistema',
        icon: 'M4 5h16v10H4zM8 19h8m-4-4v4',
    },
];
</script>

<template>
    <div class="px-4 py-2">
        <div class="mb-1 text-xs font-medium uppercase tracking-wide text-muted">
            Tema
        </div>
        <div class="flex rounded-md bg-surface-3 p-0.5">
            <button
                v-for="o in options"
                :key="o.value"
                type="button"
                class="flex flex-1 items-center justify-center gap-1 rounded px-2 py-1 text-xs font-medium transition"
                :class="
                    theme === o.value
                        ? 'bg-surface text-fg shadow-sm'
                        : 'text-muted hover:text-fg-2'
                "
                :aria-pressed="theme === o.value"
                @click="setTheme(o.value)"
            >
                <svg
                    class="h-3.5 w-3.5"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    viewBox="0 0 24 24"
                >
                    <path :d="o.icon" />
                </svg>
                {{ o.label }}
            </button>
        </div>
    </div>
</template>
