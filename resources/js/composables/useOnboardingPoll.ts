import { onUnmounted, ref, type Ref } from 'vue';

/**
 * Polling sencillo al endpoint JSON del wizard (onboarding.status):
 * `start()` lanza un ciclo cada `intervalMs` con fetch; `stop()` lo
 * detiene (también al desmontar). `data` guarda la última respuesta.
 */
export function useOnboardingPoll<T = Record<string, any>>(
    url: string | Ref<string>,
    intervalMs = 4000,
) {
    const data = ref<T | null>(null) as Ref<T | null>;
    const fetching = ref(false);
    let timer: ReturnType<typeof setTimeout> | null = null;
    let stopped = true;

    const tick = async () => {
        if (stopped) return;
        fetching.value = true;
        try {
            const res = await fetch(typeof url === 'string' ? url : url.value, {
                headers: { Accept: 'application/json' },
            });
            if (res.ok) {
                data.value = (await res.json()) as T;
            }
        } finally {
            fetching.value = false;
            if (! stopped) {
                timer = setTimeout(tick, intervalMs);
            }
        }
    };

    const start = () => {
        stopped = false;
        void tick();
    };

    const stop = () => {
        stopped = true;
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }
    };

    onUnmounted(stop);

    return { data, fetching, start, stop };
}
