<?php

use App\Services\Llm\LlmPricing;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedInteger('posts_generated')->default(0);
            $table->unsignedInteger('regenerations')->default(0);
            $table->unsignedInteger('image_refreshes')->default(0);
            $table->unsignedInteger('llm_tokens_in')->default(0);
            $table->unsignedInteger('llm_tokens_out')->default(0);
            $table->unsignedInteger('image_calls')->default(0);
            $table->unsignedInteger('extra_posts')->default(0);
            $table->unsignedInteger('extra_regenerations')->default(0);
            $table->unsignedInteger('extra_image_refreshes')->default(0);
            $table->timestamps();

            $table->unique(['brand_id', 'year', 'month']);
        });

        Schema::table('llm_logs', function (Blueprint $table) {
            $table->decimal('cost_usd', 10, 6)->default(0)->after('output_tokens');
        });

        Schema::table('monthly_plans', function (Blueprint $table) {
            $table->string('quota_notice')->nullable()->after('last_error');
        });

        $this->backfill();
    }

    /**
     * Rellena cost_usd de los logs existentes y la fila de uso del mes
     * vigente de Saberé (aproximación: tokens por mes de created_at).
     */
    private function backfill(): void
    {
        DB::table('llm_logs')
            ->select(['id', 'model', 'input_tokens', 'output_tokens'])
            ->orderBy('id')
            ->chunk(500, function ($logs) {
                foreach ($logs as $log) {
                    DB::table('llm_logs')->where('id', $log->id)->update([
                        'cost_usd' => LlmPricing::cost(
                            (string) $log->model,
                            (int) $log->input_tokens,
                            (int) $log->output_tokens
                        ),
                    ]);
                }
            });

        $sabere = DB::table('brands')->where('slug', 'sabere')->first();
        if (! $sabere) {
            return;
        }

        $posts = DB::table('posts')
            ->where('brand_id', $sabere->id)
            ->whereYear('scheduled_at', 2026)
            ->whereMonth('scheduled_at', 10)
            ->count();

        $tokens = DB::table('llm_logs')
            ->where('brand_id', $sabere->id)
            ->whereYear('created_at', 2026)
            ->whereMonth('created_at', 10)
            ->selectRaw('coalesce(sum(input_tokens),0) as tin, coalesce(sum(output_tokens),0) as tout')
            ->first();

        DB::table('brand_usage')->insertOrIgnore([
            'brand_id' => $sabere->id,
            'year' => 2026,
            'month' => 10,
            'posts_generated' => $posts,
            'regenerations' => 0,
            'image_refreshes' => 0,
            'llm_tokens_in' => (int) ($tokens->tin ?? 0),
            'llm_tokens_out' => (int) ($tokens->tout ?? 0),
            'image_calls' => 0,
            'extra_posts' => 0,
            'extra_regenerations' => 0,
            'extra_image_refreshes' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('monthly_plans', function (Blueprint $table) {
            $table->dropColumn('quota_notice');
        });
        Schema::table('llm_logs', function (Blueprint $table) {
            $table->dropColumn('cost_usd');
        });
        Schema::dropIfExists('brand_usage');
    }
};
