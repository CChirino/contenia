<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las tablas existentes ya tienen datos (son todas de Saberé): se añade
     * brand_id con default 1, se rellenan y luego se quita el default.
     */
    public function up(): void
    {
        $tables = [
            'brand_profile',
            'network_strategies',
            'content_pillars',
            'monthly_plans',
            'posts',
            'llm_logs',
        ];

        // Los únicos actuales dejan de ser globales: pasan a ser por marca.
        Schema::table('monthly_plans', function (Blueprint $table) {
            $table->dropUnique(['year', 'month']);
        });
        Schema::table('network_strategies', function (Blueprint $table) {
            $table->dropUnique(['network']);
        });

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->unsignedBigInteger('brand_id')->default(1)->index();
            });
        }

        Schema::table('brand_profile', function (Blueprint $table) {
            $table->unique('brand_id');
        });
        Schema::table('monthly_plans', function (Blueprint $table) {
            $table->unique(['brand_id', 'year', 'month']);
        });
        Schema::table('network_strategies', function (Blueprint $table) {
            $table->unique(['brand_id', 'network']);
        });

        Schema::table('calendar_events', function (Blueprint $table) {
            // null = evento global (feriados compartidos por todas las marcas).
            $table->unsignedBigInteger('brand_id')->nullable()->index();
        });

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->unsignedBigInteger('brand_id')->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('monthly_plans', function (Blueprint $table) {
            $table->dropUnique(['brand_id', 'year', 'month']);
        });
        Schema::table('network_strategies', function (Blueprint $table) {
            $table->dropUnique(['brand_id', 'network']);
        });
        Schema::table('brand_profile', function (Blueprint $table) {
            $table->dropUnique(['brand_id']);
        });
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropIndex(['brand_id']);
            $table->dropColumn('brand_id');
        });

        foreach ([
            'brand_profile',
            'network_strategies',
            'content_pillars',
            'monthly_plans',
            'posts',
            'llm_logs',
        ] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropIndex(['brand_id']);
                $table->dropColumn('brand_id');
            });
        }

        Schema::table('monthly_plans', function (Blueprint $table) {
            $table->unique(['year', 'month']);
        });
        Schema::table('network_strategies', function (Blueprint $table) {
            $table->unique('network');
        });
    }
};
