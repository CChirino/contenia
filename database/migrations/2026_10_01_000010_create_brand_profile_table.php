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
        Schema::create('brand_profile', function (Blueprint $table) {
            $table->id();
            $table->text('tone_of_voice');
            $table->text('audience');
            $table->json('base_hashtags');
            $table->string('cta');
            $table->string('url');
            $table->json('palette');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_profile');
    }
};
