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
        Schema::create('network_strategies', function (Blueprint $table) {
            $table->id();
            $table->enum('network', ['instagram', 'linkedin', 'tiktok'])->unique();
            $table->unsignedTinyInteger('posts_per_week');
            $table->json('preferred_formats');
            $table->text('focus');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('network_strategies');
    }
};
