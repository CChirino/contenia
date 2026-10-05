<?php

namespace Tests\Feature;

use App\Enums\Network;
use App\Enums\PostFormat;
use App\Enums\PostStatus;
use App\Jobs\RenderPost;
use App\Models\ContentPillar;
use App\Models\MonthlyPlan;
use App\Models\Post;
use App\Services\Images\BackgroundService;
use App\Services\Images\GeometricGenerator;
use App\Services\Images\ImageGenerator;
use App\Services\Render\FrameRenderer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Test real de render: Chromium ejecuta scripts/render.mjs y pega al servidor
 * HTTP real (misma BD y disco que la app), así que crea y borra un post real.
 * Excluido por defecto (grupo "render"); corre con --group=render.
 */
#[Group('render')]
class RenderPostRealTest extends TestCase
{
    public function test_renders_linkedin_single_image_png(): void
    {
        // Chromium pega al servidor real (BD laravel); el test corre con
        // DB_DATABASE=testing, así que se apunta la conexión a la BD real
        // mientras dure y se restaura al final.
        $originalDb = config('database.connections.mysql.database');
        config(['database.connections.mysql.database' => env('TEST_APP_DB', 'laravel')]);
        DB::purge('mysql');

        $this->app->instance(ImageGenerator::class, new GeometricGenerator);

        $post = Post::factory()->create([
            'monthly_plan_id' => MonthlyPlan::first()?->id ?? MonthlyPlan::factory()->create()->id,
            'content_pillar_id' => ContentPillar::first()?->id ?? ContentPillar::factory()->create()->id,
            'network' => Network::LinkedIn,
            'format' => PostFormat::Image,
            'status' => PostStatus::Approved,
            'hook' => 'Asistencia biométrica sin fricción',
            'copy' => 'Así registran la asistencia las escuelas modernas.',
            'design_payload' => ['slides' => [['title' => 'Asistencia biométrica', 'body' => 'Registro sin fricción para escuelas.']]],
        ]);

        try {
            (new RenderPost($post))->handle(
                app(FrameRenderer::class),
                app(BackgroundService::class),
            );

            $post->refresh();
            $this->assertSame(PostStatus::Rendered, $post->status, $post->render_error ?? '');

            $path = Storage::disk('local')->path("assets/{$post->id}/frame-01.png");
            $this->assertFileExists($path);

            $size = getimagesize($path);
            $this->assertSame(1200, $size[0]);
            $this->assertSame(1200, $size[1]);
        } finally {
            Storage::disk('local')->deleteDirectory("assets/{$post->id}");
            $post->delete();

            config(['database.connections.mysql.database' => $originalDb]);
            DB::purge('mysql');
        }
    }
}
