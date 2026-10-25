<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Primeros pasos: un usuario con `onboarded_at = null` va al wizard
 * `/onboarding` salvo rutas exentas (el propio wizard, el perfil, el
 * logout y los assets que necesita el render/portal).
 */
class EnsureOnboarded
{
    // El wizard llama a endpoints reales (marca, propuesta, contactos y
    // generación de la primera grilla) antes de tener onboarded_at.
    private const EXCEPT = [
        'onboarding.*',
        'profile.*',
        'logout',
        'login',
        'brands.logo',
        'brand.logo',
        'brands.update',
        'brands.onboarding.*',
        'brands.contacts.*',
        'plans.generate',
        'plans.status',
        'assets.*',
        'render.*',
        'review.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // "Ir a verlo" del recorrido B abre la pantalla real con ?from=onboarding
        // (la app muestra la banda "Volver a la guía"); no hay que rebotarlo.
        $fromTour = $request->isMethod('GET') && $request->query('from') === 'onboarding';

        if (
            $user !== null
            && $user->onboarded_at === null
            && ! $fromTour
            && ! $request->routeIs(self::EXCEPT)
        ) {
            return redirect()->route('onboarding.show');
        }

        return $next($request);
    }
}
