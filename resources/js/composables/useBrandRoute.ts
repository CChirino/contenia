import { usePage } from '@inertiajs/vue3';

/**
 * route() que inyecta el slug de la marca activa en las rutas /b/{brand}/...
 * Solo añade el parámetro cuando la ruta lo declara (el URI de Ziggy contiene
 * '{brand'); para el resto se comporta como el route() global.
 *
 * OJO: usa siempre parámetros nombrados ({post: id}); un escalar posicional
 * se interpretaría como `brand`.
 */
export function useBrandRoute() {
    const page = usePage();

    return ((name?: string, params?: Record<string, unknown> | number | string) => {
        // Llamadas sin nombre (route().current(...)) pasan al router global.
        if (name === undefined) {
            return route();
        }

        const brand = page.props.brand?.slug;
        const uri = (window as { Ziggy?: { routes?: Record<string, { uri?: string }> } })
            .Ziggy?.routes?.[name]?.uri;

        if (brand && uri?.includes('{brand') && typeof params === 'object' && params !== null) {
            return route(name, { brand, ...params });
        }

        return route(name, params);
    }) as typeof route;
}

