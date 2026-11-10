<?php

namespace App\Policies;

use App\Models\Brand;
use App\Models\User;

class BrandPolicy
{
    /**
     * Ver la marca: admin, o usuario asignado vía brand_user.
     */
    public function view(User $user, Brand $brand): bool
    {
        return $user->isAdmin() || $user->brands()->where('brands.id', $brand->id)->exists();
    }

    /**
     * Administración global (usuarios, catálogo de planes, cartera):
     * solo admin.
     */
    public function administer(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Crear marcas: admin o gestor.
     */
    public function create(User $user): bool
    {
        return $user->canCreateBrands();
    }

    /**
     * Gestionar una marca concreta (perfil, redes, pilares, contactos,
     * onboarding con IA, pausar): admin, o gestor asignado a ella.
     */
    public function manage(User $user, Brand $brand): bool
    {
        return $user->isAdmin()
            || ($user->isManager() && $user->brands()->where('brands.id', $brand->id)->exists());
    }

    /**
     * Vincular/desvincular operadores a una marca: mismo alcance que
     * `manage` (admin, o gestor asignado).
     */
    public function assignOperators(User $user, Brand $brand): bool
    {
        return $this->manage($user, $brand);
    }
}
