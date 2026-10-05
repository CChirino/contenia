<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('background_source')->nullable()->after('asset_path');
            $table->text('render_error')->nullable();
            $table->timestamp('rendered_at')->nullable();
            $table->json('frames')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['background_source', 'render_error', 'rendered_at', 'frames']);
        });
    }
};
