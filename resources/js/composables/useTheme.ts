import { computed, ref } from 'vue';

export type Theme = 'light' | 'dark' | 'system';

const STORAGE_KEY = 'theme';
const media = window.matchMedia('(prefers-color-scheme: dark)');

const stored = localStorage.getItem(STORAGE_KEY);
const theme = ref<Theme>(
    stored === 'light' || stored === 'dark' || stored === 'system' ? stored : 'system',
);
const resolved = ref<'light' | 'dark'>(media.matches ? 'dark' : 'light');

function apply() {
    resolved.value = theme.value === 'system' ? (media.matches ? 'dark' : 'light') : theme.value;
    document.documentElement.classList.toggle('dark', resolved.value === 'dark');
}

media.addEventListener('change', () => {
    if (theme.value === 'system') {
        apply();
    }
});

apply();

export function useTheme() {
    function setTheme(value: Theme) {
        theme.value = value;
        localStorage.setItem(STORAGE_KEY, value);
        apply();
    }

    return {
        theme: computed(() => theme.value),
        resolved: computed(() => resolved.value),
        setTheme,
    };
}
