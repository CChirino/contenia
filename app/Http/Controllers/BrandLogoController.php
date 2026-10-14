<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BrandLogoController extends Controller
{
    /**
     * Logo de la marca para el render y el selector. Público y solo lectura:
     * Chromium lo descarga sin sesión durante el render.
     */
    public function __invoke(Brand $brand): BinaryFileResponse|Response
    {
        if ($brand->logo_path && Storage::disk('local')->exists($brand->logo_path)) {
            return response()->file(Storage::disk('local')->path($brand->logo_path));
        }

        // El SVG del repo es de Saberé: solo ella lo usa de fallback.
        if ($brand->id === 1) {
            return response()->file(resource_path('brand/logo.svg'), [
                'Content-Type' => 'image/svg+xml',
            ]);
        }

        return response($this->initialLogo($brand), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Logo genérico: círculo con el acento de la marca y su inicial.
     */
    private function initialLogo(Brand $brand): string
    {
        $accent = htmlspecialchars($brand->palette['accent'] ?? '#27C2A5', ENT_QUOTES);
        $dark = htmlspecialchars($brand->palette['dark'] ?? '#1A2A42', ENT_QUOTES);
        $initial = mb_strtoupper(mb_substr($brand->name, 0, 1));

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="64" height="64">
  <circle cx="32" cy="32" r="32" fill="{$accent}"/>
  <text x="32" y="42" font-family="Inter, sans-serif" font-size="32" font-weight="bold" fill="{$dark}" text-anchor="middle">{$initial}</text>
</svg>
SVG;
    }
}
