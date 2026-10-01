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
        Schema::create('llm_logs', function (Blueprint $table) {
            $table->id();
            $table->string('purpose');
            $table->longText('prompt');
            $table->longText('response');
            $table->unsignedInteger('input_tokens');
            $table->unsignedInteger('output_tokens');
            $table->string('model');
            $table->unsignedInteger('duration_ms');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('llm_logs');
    }
};
