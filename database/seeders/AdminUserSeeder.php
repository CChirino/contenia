<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Crea el usuario administrador único de la aplicación.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@sabere.com'],
            [
                'name' => 'Admin Saberé',
                'role' => UserRole::Admin,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                // El admin semilla no es un "usuario nuevo": ya conoce la app.
                'onboarded_at' => now(),
            ]
        );

        // La migración asigna al admin a Saberé solo si el usuario ya
        // existía; en instalaciones nuevas no había ninguno y el
        // seeder debe hacer la asignación.
        $user = User::where('email', 'admin@sabere.com')->first();
        $user?->brands()->syncWithoutDetaching(\App\Models\Brand::pluck('id')->all());
    }
}
