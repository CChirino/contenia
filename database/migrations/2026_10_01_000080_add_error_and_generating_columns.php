<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('monthly_plans', function (Blueprint $table) {
            $table->text('last_error')->nullable()->after('generated_at');
            $table->timestamp('generating_at')->nullable()->after('last_error');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->text('regeneration_error')->nullable()->after('error');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monthly_plans', function (Blueprint $table) {
            $table->dropColumn(['last_error', 'generating_at']);
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('regeneration_error');
        });
    }
};
