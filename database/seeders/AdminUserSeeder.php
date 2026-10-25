<?php

namespace Database\Seeders;

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
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                // El admin semilla no es un "usuario nuevo": ya conoce la app.
                'onboarded_at' => now(),
            ]
        );
    }
}
