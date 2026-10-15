<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nuevo estado client_approved (MySQL enum; SQLite es texto libre).
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE posts MODIFY COLUMN status ENUM('drafted','approved','rendered','client_approved','scheduled','published','failed') NOT NULL DEFAULT 'drafted'");
        }

        Schema::create('brand_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('whatsapp')->nullable();
            $table->timestamps();

            $table->index('brand_id');
            $table->unique(['brand_id', 'email']);
        });

        Schema::create('review_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->json('sent_to');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->text('general_comment')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('brand_id');
        });

        Schema::create('review_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('decision')->default('pending');
            $table->text('comment')->nullable();
            $table->date('week_from');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['review_request_id', 'post_id']);
            $table->index('post_id');
        });

        Schema::create('review_week_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_request_id')->constrained()->cascadeOnDelete();
            $table->date('week_from');
            $table->text('comment');
            $table->timestamps();

            $table->unique(['review_request_id', 'week_from']);
        });

        // Los posts ya renderizados de marcas sin aprobación de cliente
        // (Saberé) quedan listos para programar.
        DB::table('posts')
            ->where('status', 'rendered')
            ->whereIn(
                'brand_id',
                DB::table('brands')->where('requires_client_approval', false)->pluck('id')
            )
            ->update(['status' => 'client_approved']);
    }

    public function down(): void
    {
        Schema::dropIfExists('review_week_comments');
        Schema::dropIfExists('review_items');
        Schema::dropIfExists('review_requests');
        Schema::dropIfExists('brand_contacts');
    }
};
