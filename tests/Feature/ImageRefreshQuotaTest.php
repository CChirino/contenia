<?php

namespace Tests\Feature;

use App\Enums\Network;
use App\Enums\PostFormat;
use App\Enums\PostStatus;
use App\Jobs\RenderPost;
use App\Models\Brand;
use App\Models\ContentPillar;
use App\Models\MonthlyPlan;
use App\Models\Plan;
use App\Models\Post;
use App\Services\Images\FakePhotoSource;
use App\Services\Images\PhotoSource;
use App\Services\Render\FakeFrameRenderer;
use App\Services\Render\FrameRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageRefreshQuotaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->app->instance(FrameRenderer::class, new FakeFrameRenderer);
    }

    private function brandConPlan(?int $refreshes): Brand
    {
        $plan = Plan::factory()->create([
            'posts_per_month' => null,
            'regenerations_per_month' => null,
            'image_refreshes_per_month' => $refreshes,
            'networks' => ['instagram', 'linkedin', 'tiktok'],
        ]);

        return Brand::factory()->create(['plan_id' => $plan->id]);
    }

    private function postRenderizable(Brand $brand, array $attrs = []): Post
    {
        $plan = MonthlyPlan::factory()->create([
            'brand_id' => $brand->id, 'year' => 2026, 'month' => 10,
        ]);
        $pillar = ContentPillar::factory()->create([
            'brand_id' => $brand->id, 'name' => 'Pilar', 'weight' => 100,
        ]);

        return Post::factory()->create(array_merge([
            'brand_id' => $brand->id,
            'monthly_plan_id' => $plan->id,
            'content_pillar_id' => $pillar->id,
            'network' => Network::Instagram,
            'format' => PostFormat::Image,
            'status' => PostStatus::Approved,
            'photo_keywords' => null,
        ], $attrs));
    }

    public function test_el_controlador_devuelve_422_con_cupo_de_fondos_agotado(): void
    {
        $brand = $this->brandConPlan(1);
        $post = $this->postRenderizable($brand);
        $brand->usageFor(2026, 10)->update(['image_refreshes' => 1]);

        $this->actingAsAdminFor($brand);
        $this->post(route('posts.render', ['brand' => $brand->slug, 'post' => $post->id]), [
            'fresh_background' => true,
        ])->assertSessionHasErrors('quota');
    }

    public function test_render_normal_no_consume_cupo_de_fondos(): void
    {
        $brand = $this->brandConPlan(0);
        $post = $this->postRenderizable($brand);

        // Sin fresh_background: no consume aunque el cupo sea 0.
        RenderPost::dispatchSync($post);

        $this->assertSame(0, (int) $brand->usageFor(2026, 10)->image_refreshes);
        $this->assertSame(0, (int) $brand->usageFor(2026, 10)->image_calls);
        $this->assertNotNull($post->fresh()->rendered_at);
    }

    public function test_fondo_nuevo_ia_incrementa_ambos_contadores(): void
    {
        $brand = $this->brandConPlan(2);
        $post = $this->postRenderizable($brand);

        // Sin photo_keywords → cae al generador IA (driver 'fake' en tests).
        RenderPost::dispatchSync($post, freshBackground: true);

        $usage = $brand->fresh()->usageFor(2026, 10);
        $this->assertSame(1, (int) $usage->image_refreshes);
        $this->assertSame(1, (int) $usage->image_calls);
        $this->assertSame('fake', $post->fresh()->background_source);
    }

    public function test_pixabay_no_cuenta_como_llamada_ia(): void
    {
        $brand = $this->brandConPlan(2);
        $post = $this->postRenderizable($brand, ['photo_keywords' => 'aula']);

        $this->app->instance(PhotoSource::class, new FakePhotoSource);

        RenderPost::dispatchSync($post, freshBackground: true);

        $usage = $brand->fresh()->usageFor(2026, 10);
        $this->assertSame(0, (int) $usage->image_calls);
        // El fondo vino de Pixabay: tampoco consume la cuota de fondos nuevos IA.
        $this->assertSame(0, (int) $usage->image_refreshes);
        $this->assertSame('pixabay', $post->fresh()->background_source);
    }

    public function test_el_job_aborta_si_el_cupo_se_agoto_tras_el_dispatch(): void
    {
        $brand = $this->brandConPlan(1);
        $post = $this->postRenderizable($brand);

        // Concurrencia: otro render consumió el último fondo nuevo.
        $brand->usageFor(2026, 10)->update(['image_refreshes' => 1]);

        RenderPost::dispatchSync($post, freshBackground: true);

        $this->assertNull($post->fresh()->rendered_at);
        $this->assertNotNull($post->fresh()->render_error);
        $this->assertSame(1, (int) $brand->fresh()->usageFor(2026, 10)->image_refreshes);
        $this->assertSame(0, (int) $brand->fresh()->usageFor(2026, 10)->image_calls);
    }
}
