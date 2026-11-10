<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rutas de gestión de marcas (/brands): admin o gestor. El gestor actúa
 * solo sobre sus marcas asignadas (lo verifica `can:manage,brand`).
 */
class EnsureManager
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->canCreateBrands(), 403);

        return $next($request);
    }
}
