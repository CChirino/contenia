<?php

namespace App\Http\Middleware;

use App\Models\Brand;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            // Closures: se evalúan al construir la respuesta, cuando el
            // middleware `brand` ya dejó la marca activa en el contenedor.
            'brand' => fn () => app()->bound('brand')
                ? [
                    ...app('brand')->only('id', 'slug', 'name', 'palette'),
                    'logo_url' => route('brands.logo', ['brand' => app('brand')->slug]),
                ]
                : null,
            'brands' => fn () => $this->assignedBrands($request),
        ];
    }

    /**
     * Marcas visibles para el selector: todas para admin, asignadas para
     * operadores.
     *
     * @return array<int, array{id: int, slug: string, name: string}>
     */
    private function assignedBrands(Request $request): array
    {
        $user = $request->user();

        if (! $user) {
            return [];
        }

        $brands = $user->isAdmin()
            ? Brand::orderBy('name')->get(['id', 'slug', 'name'])
            : $user->brands()->orderBy('name')->get(['brands.id', 'slug', 'name']);

        return $brands->map(fn (Brand $brand) => [
            ...$brand->only('id', 'slug', 'name'),
            'logo_url' => route('brands.logo', ['brand' => $brand->slug]),
        ])->all();
    }
}
