<?php

namespace App\Policies;

use App\Models\Brand;
use App\Models\User;

class BrandPolicy
{
    /**
     * Ver la marca: admin, u operador asignado vía brand_user.
     */
    public function view(User $user, Brand $brand): bool
    {
        return $user->isAdmin() || $user->brands()->where('brands.id', $brand->id)->exists();
    }

    /**
     * Administrar marcas y usuarios: solo admin.
     */
    public function manage(User $user): bool
    {
        return $user->isAdmin();
    }
}
