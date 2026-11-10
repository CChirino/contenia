<?php

namespace Tests;

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\URL;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // La migración siembra Saberé (id 1): se deja como marca activa por
        // defecto en tests (las factories asignan brand_id al crear) y como
        // default de las URLs para no repetir `brand` en cada route().
        // Los tests que verifiquen el comportamiento "sin marca" pueden
        // liberarla con app()->forgetInstance('brand').
        if ($brand = Brand::where('slug', 'sabere')->first()) {
            app()->instance('brand', $brand);
            URL::defaults(['brand' => 'sabere']);
        }
    }

    /**
     * Los tests que autentican usuarios sin tocar `onboarded_at` quieren un
     * usuario ya incorporado (no el wizard). El asistente se prueba pasando
     * 'onboarded_at' => null explícito a la factory.
     */
    public function actingAs($user, $guard = null)
    {
        if (
            $user instanceof User
            && ! array_key_exists('onboarded_at', $user->getAttributes())
        ) {
            $user->forceFill(['onboarded_at' => now()])->save();
        }

        return parent::actingAs($user, $guard);
    }

    /**
     * Admin autenticado con la marca activa en el contenedor (Saberé si no se
     * indica otra). Devuelve el usuario; $brand queda como marca activa.
     */
    protected function actingAsAdminFor(?Brand $brand = null): User
    {
        $brand ??= Brand::where('slug', 'sabere')->first() ?? Brand::factory()->create();

        $user = User::factory()->create(['role' => UserRole::Admin]);
        $user->brands()->syncWithoutDetaching([$brand->id]);

        app()->instance('brand', $brand);
        URL::defaults(['brand' => $brand->slug]);

        $this->actingAs($user);

        return $user;
    }

    /**
     * Gestor autenticado asignado a la marca indicada (o Saberé).
     */
    protected function actingAsManagerFor(?Brand $brand = null): User
    {
        $brand ??= Brand::where('slug', 'sabere')->first() ?? Brand::factory()->create();

        $user = User::factory()->create(['role' => UserRole::Manager]);
        $user->brands()->syncWithoutDetaching([$brand->id]);

        app()->instance('brand', $brand);
        URL::defaults(['brand' => $brand->slug]);

        $this->actingAs($user);

        return $user;
    }

    /**
     * Operador autenticado asignado a la marca indicada (o Saberé).
     */
    protected function actingAsOperatorFor(?Brand $brand = null): User
    {
        $brand ??= Brand::where('slug', 'sabere')->first() ?? Brand::factory()->create();

        $user = User::factory()->create(['role' => UserRole::Operator]);
        $user->brands()->syncWithoutDetaching([$brand->id]);

        app()->instance('brand', $brand);
        URL::defaults(['brand' => $brand->slug]);

        $this->actingAs($user);

        return $user;
    }
}
