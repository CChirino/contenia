<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BrandLogoController extends Controller
{
    /**
     * Logo de la marca para el render y el selector. Público y solo lectura:
     * Chromium lo descarga sin sesión durante el render.
     */
    public function __invoke(Brand $brand): BinaryFileResponse
    {
        if ($brand->logo_path && Storage::disk('local')->exists($brand->logo_path)) {
            return response()->file(Storage::disk('local')->path($brand->logo_path));
        }

        return response()->file(resource_path('brand/logo.svg'), [
            'Content-Type' => 'image/svg+xml',
        ]);
    }
}
