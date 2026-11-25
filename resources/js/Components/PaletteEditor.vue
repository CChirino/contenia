<script setup lang="ts">
import { computed } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import { contrast, paletteRoles, type PaletteRoles } from '@/utils/brandPalette';

const model = defineModel<{ primary: string[]; secondary: string[] }>({
    required: true,
});

const FALLBACKS = ['#4F46E5', '#8B5CF6', '#14122B', '#F5F4FF'];

const setPrimary = (i: number, v: string) => {
    const p = [...model.value.primary];
    while (p.length < 3) p.push(FALLBACKS[p.length] ?? '#14122B');
    p[i] = v.toUpperCase();
    model.value = { ...model.value, primary: p };
};

const setSecondary = (i: number, v: string) => {
    const s = [...model.value.secondary];
    s[i] = v.toUpperCase();
    model.value = { ...model.value, secondary: s };
};

const addSecondary = () => {
    if (model.value.secondary.length >= 3) return;
    model.value = { ...model.value, secondary: [...model.value.secondary, '#F5F4FF'] };
};

const removeSecondary = (i: number) => {
    const s = [...model.value.secondary];
    s.splice(i, 1);
    model.value = { ...model.value, secondary: s };
};

const roles = computed<PaletteRoles>(() => paletteRoles(model.value));

const roleLabels: Record<keyof PaletteRoles, string> = {
    dark: 'Fondo oscuro',
    light: 'Fondo claro / texto',
    accent: 'Color principal',
    secondary: 'Color de apoyo',
};

// Texto de cada muestra: el de mejor contraste entre blanco y el fondo oscuro.
const tileText = (hex: string) => (contrast(hex, '#FFFFFF') >= contrast(hex, roles.value.dark) ? '#FFFFFF' : roles.value.dark);
</script>

<template>
    <div class="space-y-4" data-palette-editor>
        <div data-primary>
            <InputLabel value="Colores primarios" />
            <p class="mt-0.5 text-xs text-muted">
                Ordenados por importancia: el 1 es el color principal de la marca.
            </p>
            <div class="mt-2 flex flex-wrap gap-3">
                <div v-for="i in 3" :key="`p-${i}`" class="flex items-center gap-2">
                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-surface-3 text-xs font-semibold text-fg-2">
                        {{ i }}
                    </span>
                    <input
                        :id="`pe-primary-${i}`"
                        type="color"
                        :value="model.primary[i - 1] ?? '#4F46E5'"
                        class="h-9 w-12 cursor-pointer rounded-md border border-line bg-surface-2 p-0.5"
                        @input="setPrimary(i - 1, ($event.target as HTMLInputElement).value)"
                    />
                    <input
                        type="text"
                        :value="model.primary[i - 1] ?? ''"
                        maxlength="7"
                        class="w-20 rounded-md border border-line bg-surface-2 px-2 py-1 font-mono text-xs uppercase text-fg"
                        @input="setPrimary(i - 1, ($event.target as HTMLInputElement).value)"
                    />
                </div>
            </div>
        </div>

        <div data-secondary>
            <InputLabel value="Colores secundarios (opcional)" />
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <div v-for="(c, i) in model.secondary" :key="`s-${i}`" class="flex items-center gap-1">
                    <input
                        type="color"
                        :value="c"
                        class="h-9 w-12 cursor-pointer rounded-md border border-line bg-surface-2 p-0.5"
                        @input="setSecondary(i, ($event.target as HTMLInputElement).value)"
                    />
                    <input
                        type="text"
                        :value="c"
                        maxlength="7"
                        class="w-20 rounded-md border border-line bg-surface-2 px-2 py-1 font-mono text-xs uppercase text-fg"
                        @input="setSecondary(i, ($event.target as HTMLInputElement).value)"
                    />
                    <button
                        type="button"
                        class="rounded-md p-1 text-muted hover:text-fg"
                        title="Quitar"
                        @click="removeSecondary(i)"
                    >
                        ×
                    </button>
                </div>
                <button
                    v-if="model.secondary.length < 3"
                    type="button"
                    class="rounded-md border border-dashed border-line px-2.5 py-1.5 text-xs font-medium text-fg-2 hover:bg-surface-3"
                    @click="addSecondary"
                >
                    + Añadir
                </button>
            </div>
        </div>

        <div>
            <InputLabel value="Así se usarán en los diseños" />
            <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                <div
                    v-for="(label, role) in roleLabels"
                    :key="role"
                    data-role="palette-role"
                    :data-color="roles[role]"
                    class="rounded-lg border border-line p-2"
                    :style="{ backgroundColor: roles[role] }"
                >
                    <div
                        class="text-[11px] font-semibold"
                        :style="{ color: tileText(roles[role]) }"
                    >
                        {{ label }}
                    </div>
                    <div
                        class="font-mono text-[10px] uppercase opacity-80"
                        :style="{ color: tileText(roles[role]) }"
                    >
                        {{ roles[role] }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
