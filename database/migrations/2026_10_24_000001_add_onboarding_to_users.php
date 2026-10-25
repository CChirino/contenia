<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('onboarded_at')->nullable()->after('last_brand_id');
            $table->json('onboarding_state')->nullable()->after('onboarded_at');
        });

        // Nadie que ya use la app ve el wizard a la fuerza: lo abren
        // desde "Guía de inicio" del menú (que quita onboarded_at).
        DB::table('users')->whereNull('onboarded_at')->update(['onboarded_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['onboarded_at', 'onboarding_state']);
        });
    }
};
