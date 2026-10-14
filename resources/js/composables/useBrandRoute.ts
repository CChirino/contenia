import { usePage } from '@inertiajs/vue3';

/**
 * route() que inyecta el slug de la marca activa en las rutas /b/{brand}/...
 * (todas cuelgan de los prefijos plans./posts./settings.). Para el resto se
 * comporta como el route() global.
 *
 * En páginas sin marca activa (/brands, /users, /profile) se usa la primera
 * marca asignada para que los enlaces del nav sigan resolviendo.
 *
 * OJO: usa siempre parámetros nombrados ({post: id}); un escalar posicional
 * se interpretaría como `brand`.
 */
const BRAND_ROUTE_PREFIXES = ['plans.', 'posts.', 'settings.'];

export function useBrandRoute() {
    const page = usePage();

    return ((name?: string, params?: Record<string, unknown> | number | string) => {
        // Llamadas sin nombre (route().current(...)) pasan al router global.
        if (name === undefined) {
            return route();
        }

        const brand = page.props.brand?.slug ?? page.props.brands?.[0]?.slug;

        if (
            brand
            && BRAND_ROUTE_PREFIXES.some((p) => name.startsWith(p))
            && (params === undefined || (typeof params === 'object' && params !== null))
        ) {
            return route(name, { brand, ...(params ?? {}) });
        }

        return route(name, params);
    }) as typeof route;
}
