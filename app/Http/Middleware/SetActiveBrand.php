<?php

namespace App\Http\Middleware;

use App\Models\Brand;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve la marca de la ruta (/b/{brand}), verifica el acceso del usuario
 * y la fija como activa en el contenedor para que BrandScope filtre.
 *
 * Se ejecuta ANTES de SubstituteBindings (ver bootstrap/app.php) para que el
 * route-model-binding de posts/pilares/etc. ya aplique el scope de marca.
 */
class SetActiveBrand
{
    public function handle(Request $request, Closure $next): Response
    {
        $param = $request->route('brand');

        $brand = $param instanceof Brand
            ? $param
            : Brand::where('slug', (string) $param)->first();

        abort_unless($brand, 404);
        abort_unless($request->user()?->can('view', $brand), 403);

        app()->instance('brand', $brand);
        URL::defaults(['brand' => $brand->slug]);

        $user = $request->user();
        if ($user->last_brand_id !== $brand->id) {
            $user->forceFill(['last_brand_id' => $brand->id])->save();
        }

        return $next($request);
    }
}
