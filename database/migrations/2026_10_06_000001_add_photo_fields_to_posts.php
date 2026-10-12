<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('photo_keywords', 120)->nullable()->after('background_prompt');
            $table->string('icon', 32)->nullable()->after('photo_keywords');
            $table->json('background_meta')->nullable()->after('background_source');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['photo_keywords', 'icon', 'background_meta']);
        });
    }
};
