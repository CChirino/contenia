<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Jobs\RegeneratePost;
use App\Models\Brand;
use App\Models\ContentPillar;
use App\Models\MonthlyPlan;
use App\Models\Plan;
use App\Models\Post;
use App\Services\Llm\FakeLlmClient;
use App\Services\Llm\LlmClient;
use App\Services\Llm\LlmException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegeneratePostQuotaTest extends TestCase
{
    use RefreshDatabase;

    private function brandConPlan(int $regenerations): Brand
    {
        $plan = Plan::factory()->create([
            'name' => 'Básico test',
            'posts_per_month' => null,
            'regenerations_per_month' => $regenerations,
            'image_refreshes_per_month' => null,
            'networks' => ['instagram', 'linkedin', 'tiktok'],
        ]);

        return Brand::factory()->create(['plan_id' => $plan->id]);
    }

    private function postDrafted(Brand $brand): Post
    {
        $plan = MonthlyPlan::factory()->create([
            'brand_id' => $brand->id, 'year' => 2026, 'month' => 10,
        ]);
        $pillar = ContentPillar::factory()->create([
            'brand_id' => $brand->id, 'name' => 'Pilar', 'weight' => 100,
        ]);

        return Post::factory()->create([
            'brand_id' => $brand->id,
            'monthly_plan_id' => $plan->id,
            'content_pillar_id' => $pillar->id,
            'status' => PostStatus::Drafted,
            'hook' => 'Hook original',
        ]);
    }

    public function test_el_controlador_devuelve_422_con_cupo_agotado(): void
    {
        $brand = $this->brandConPlan(1);
        $post = $this->postDrafted($brand);
        $brand->usageFor(2026, 10)->update(['regenerations' => 1]);

        $this->actingAsAdminFor($brand);
        $this->post(route('posts.regenerate', ['brand' => $brand->slug, 'post' => $post->id]))
            ->assertSessionHasErrors('quota');

        $this->assertSame('Hook original', $post->fresh()->hook);
    }

    public function test_el_job_recorta_concurrencia_y_no_toca_el_post(): void
    {
        $brand = $this->brandConPlan(1);
        $post = $this->postDrafted($brand);

        // Otro job consumió el cupo entre el dispatch y la ejecución.
        $brand->usageFor(2026, 10)->update(['regenerations' => 1]);

        $fake = (new FakeLlmClient)->withResponse([
            'hook' => 'Hook nuevo', 'copy' => 'Copy nuevo',
        ]);
        $this->app->instance(LlmClient::class, $fake);

        RegeneratePost::dispatchSync($post);

        $this->assertSame('Hook original', $post->fresh()->hook);
        $this->assertSame(1, (int) $brand->fresh()->usageFor(2026, 10)->regenerations);
    }

    public function test_exito_incrementa_el_contador_en_el_mes_del_contenido(): void
    {
        $brand = $this->brandConPlan(2);
        $post = $this->postDrafted($brand);

        $fake = (new FakeLlmClient)->withResponse([
            'hook' => 'Hook nuevo', 'copy' => 'Copy nuevo',
            'hashtags' => ['#a'], 'design_payload' => ['x' => 1],
        ]);
        $this->app->instance(LlmClient::class, $fake);

        RegeneratePost::dispatchSync($post);

        $this->assertSame('Hook nuevo', $post->fresh()->hook);
        $this->assertSame(1, (int) $brand->fresh()->usageFor(2026, 10)->regenerations);
    }

    public function test_fallo_del_llm_no_incrementa(): void
    {
        $brand = $this->brandConPlan(2);
        $post = $this->postDrafted($brand);

        $this->app->instance(LlmClient::class, (new FakeLlmClient)->withException('caído'));

        try {
            RegeneratePost::dispatchSync($post);
            $this->fail('Debió relanzar la excepción');
        } catch (LlmException) {
        }

        $this->assertSame('Hook original', $post->fresh()->hook);
        $this->assertSame(0, (int) $brand->fresh()->usageFor(2026, 10)->regenerations);
        $this->assertSame('caído', $post->fresh()->regeneration_error);
    }

    public function test_plan_ilimitado_regenera_siempre(): void
    {
        $brand = $this->brandConPlan(0); // límite 0... → ajustamos a null abajo
        $brand->plan()->update(['regenerations_per_month' => null]);
        $brand->usageFor(2026, 10)->update(['regenerations' => 99]);

        $post = $this->postDrafted($brand);
        $this->app->instance(LlmClient::class, (new FakeLlmClient)->withResponse([
            'hook' => 'H', 'copy' => 'C',
        ]));

        RegeneratePost::dispatchSync($post);

        $this->assertSame('H', $post->fresh()->hook);
        $this->assertSame(100, (int) $brand->fresh()->usageFor(2026, 10)->regenerations);
    }
}
