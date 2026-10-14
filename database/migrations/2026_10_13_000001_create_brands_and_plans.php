<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('posts_per_month')->nullable();
            $table->unsignedInteger('regenerations_per_month')->nullable();
            $table->unsignedInteger('image_refreshes_per_month')->nullable();
            $table->json('networks');
            $table->string('price_label')->nullable();
            $table->timestamps();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo_path')->nullable();
            $table->json('palette');
            $table->string('website_url')->nullable();
            $table->enum('status', ['active', 'paused'])->default('active');
            $table->foreignId('plan_id')->constrained('plans');
            $table->boolean('requires_client_approval')->default(true);
            $table->json('onboarding_proposal')->nullable();
            $table->timestamps();
        });

        Schema::create('brand_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['brand_id', 'user_id']);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'operator'])->default('operator');
            $table->foreignId('last_brand_id')->nullable()->constrained('brands')->nullOnDelete();
        });

        $now = now();
        $allNetworks = json_encode(['instagram', 'linkedin', 'tiktok']);

        DB::table('plans')->insert([
            [
                'name' => 'Básico',
                'posts_per_month' => 12,
                'regenerations_per_month' => 10,
                'image_refreshes_per_month' => 10,
                'networks' => json_encode(['instagram', 'linkedin']),
                'price_label' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Pro',
                'posts_per_month' => 30,
                'regenerations_per_month' => 40,
                'image_refreshes_per_month' => 40,
                'networks' => $allNetworks,
                'price_label' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Interno',
                'posts_per_month' => null,
                'regenerations_per_month' => null,
                'image_refreshes_per_month' => null,
                'networks' => $allNetworks,
                'price_label' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $interno = DB::table('plans')->where('name', 'Interno')->value('id');

        DB::table('brands')->insert([
            'id' => 1,
            'name' => 'Saberé',
            'slug' => 'sabere',
            'logo_path' => null,
            'palette' => json_encode([
                'dark' => '#1A2A42',
                'light' => '#F8F5F0',
                'accent' => '#27C2A5',
                'secondary' => '#7854D2',
            ]),
            'website_url' => 'https://sabere.com',
            'status' => 'active',
            'plan_id' => $interno,
            'requires_client_approval' => false,
            'onboarding_proposal' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // El usuario existente es admin y queda asignado a Saberé.
        DB::table('users')->update(['role' => 'admin']);
        DB::table('users')->whereNull('last_brand_id')->update(['last_brand_id' => 1]);

        foreach (DB::table('users')->pluck('id') as $userId) {
            DB::table('brand_user')->insertOrIgnore([
                'brand_id' => 1,
                'user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['last_brand_id']);
            $table->dropColumn(['role', 'last_brand_id']);
        });

        Schema::dropIfExists('brand_user');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('plans');
    }
};
