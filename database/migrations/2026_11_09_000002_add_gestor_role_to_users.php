<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nuevo rol `gestor` entre admin y operador. MySQL no altera un ENUM con
 * `$table->enum()->change()` sin doctrine/dbal: se hace con ALTER directo.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','gestor','operator') NOT NULL DEFAULT 'operator'");
    }

    public function down(): void
    {
        // Antes de estrechar el enum, los gestores pasan a operador.
        DB::table('users')->where('role', 'gestor')->update(['role' => 'operator']);

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','operator') NOT NULL DEFAULT 'operator'");
    }
};
