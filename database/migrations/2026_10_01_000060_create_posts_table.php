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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_plan_id')->constrained()->cascadeOnDelete();
            $table->dateTime('scheduled_at');
            $table->enum('network', ['instagram', 'linkedin', 'tiktok']);
            $table->foreignId('content_pillar_id')->nullable()->constrained()->nullOnDelete();
            $table->string('format');
            $table->string('hook');
            $table->text('copy');
            $table->json('hashtags');
            $table->json('design_payload');
            $table->text('background_prompt')->nullable();
            $table->string('template')->nullable();
            $table->string('asset_path')->nullable();
            $table->string('external_id')->nullable();
            $table->string('permalink')->nullable();
            $table->enum('status', ['drafted', 'approved', 'rendered', 'scheduled', 'published', 'failed'])
                ->default('drafted');
            $table->text('error')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
