<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_profile', function (Blueprint $table) {
            $table->text('description')->nullable()->after('brand_id');
        });

        DB::table('brand_profile')->where('brand_id', 1)->update([
            'description' => 'Plataforma de gestión escolar para colegios privados en Venezuela.',
        ]);
    }

    public function down(): void
    {
        Schema::table('brand_profile', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
