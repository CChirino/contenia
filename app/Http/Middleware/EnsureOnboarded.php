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
    private const EXCEPT = [
        'onboarding.*',
        'profile.*',
        'logout',
        'login',
        'brands.logo',
        'brand.logo',
        'assets.*',
        'render.*',
        'review.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user !== null
            && $user->onboarded_at === null
            && ! $request->routeIs(self::EXCEPT)
        ) {
            return redirect()->route('onboarding.show');
        }

        return $next($request);
    }
}
