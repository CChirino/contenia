<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->timestamp('onboarded_at')->nullable()->after('onboarding_proposal');
        });

        // Memoria de la marca: pautas propuestas por la IA o el feedback del
        // cliente y confirmadas por el operador (spec §3.1, §8.2).
        Schema::create('brand_guidelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('text', 200);
            $table->enum('source', ['ai_distilled', 'client_feedback', 'operator']);
            $table->enum('status', ['pending', 'active', 'pending_retire', 'retired', 'discarded'])
                ->default('pending');
            $table->unsignedSmallInteger('weight')->default(1);
            $table->unsignedInteger('evidence_count')->default(1);
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->timestamps();

            $table->index(['brand_id', 'status']);
        });

        // Diffs de las ediciones del operador a hook/copy: evidencia para
        // la memoria (spec §8.2).
        Schema::create('post_edits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('field', 10);
            $table->text('before')->nullable();
            $table->text('after')->nullable();
            $table->foreignId('review_request_id')->nullable()
                ->constrained('review_requests')->nullOnDelete();
            $table->timestamps();

            $table->index('post_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_edits');
        Schema::dropIfExists('brand_guidelines');

        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('onboarded_at');
        });
    }
};
