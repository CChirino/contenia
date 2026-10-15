<?php

namespace Tests\Feature;

use App\Enums\Network;
use App\Enums\QuotaAction;
use App\Exceptions\QuotaExceededException;
use App\Models\Brand;
use App\Models\Plan;
use App\Services\Quota\QuotaGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class QuotaGuardTest extends TestCase
{
    use RefreshDatabase;

    private function brandConPlan(array $planAttrs): Brand
    {
        $plan = Plan::factory()->create(array_merge([
            'posts_per_month' => 12,
            'regenerations_per_month' => 4,
            'image_refreshes_per_month' => 3,
            'networks' => ['instagram', 'linkedin'],
        ], $planAttrs));

        return Brand::factory()->create(['plan_id' => $plan->id]);
    }

    public function test_limite_uso_y_restante(): void
    {
        $brand = $this->brandConPlan(['posts_per_month' => 5]);
        $quota = app(QuotaGuard::class);

        $this->assertSame(5, $quota->limit($brand, QuotaAction::PostsGenerated, 2026, 10));
        $this->assertSame(0, $quota->used($brand, QuotaAction::PostsGenerated, 2026, 10));
        $this->assertSame(5, $quota->remaining($brand, QuotaAction::PostsGenerated, 2026, 10));

        $brand->usageFor(2026, 10)->update(['posts_generated' => 4]);
        $this->assertSame(1, $quota->remaining($brand, QuotaAction::PostsGenerated, 2026, 10));
    }

    public function test_los_extras_amplian_el_limite(): void
    {
        $brand = $this->brandConPlan(['posts_per_month' => 12]);
        $brand->usageFor(2026, 10)->update(['posts_generated' => 12, 'extra_posts' => 3]);

        $quota = app(QuotaGuard::class);
        $this->assertSame(15, $quota->limit($brand, QuotaAction::PostsGenerated, 2026, 10));
        $this->assertSame(3, $quota->remaining($brand, QuotaAction::PostsGenerated, 2026, 10));
    }

    public function test_limite_null_es_ilimitado(): void
    {
        $brand = $this->brandConPlan(['posts_per_month' => null]);
        $quota = app(QuotaGuard::class);

        $this->assertNull($quota->limit($brand, QuotaAction::PostsGenerated, 2026, 10));
        $this->assertNull($quota->remaining($brand, QuotaAction::PostsGenerated, 2026, 10));

        $brand->usageFor(2026, 10)->update(['posts_generated' => 999]);
        $quota->check($brand, QuotaAction::PostsGenerated, 2026, 10); // no lanza
        $this->assertTrue(true);
    }

    public function test_check_lanza_validation_exception_con_clave_quota(): void
    {
        $brand = $this->brandConPlan(['regenerations_per_month' => 2, 'name' => 'Básico']);
        $brand->usageFor(2026, 10)->update(['regenerations' => 2]);

        try {
            app(QuotaGuard::class)->check($brand, QuotaAction::Regenerations, 2026, 10);
            $this->fail('Debió lanzar QuotaExceededException');
        } catch (QuotaExceededException $e) {
            $this->assertInstanceOf(ValidationException::class, $e);
            $errors = $e->errors();
            $this->assertArrayHasKey('quota', $errors);
            $this->assertStringContainsString('regeneraciones', $errors['quota'][0]);
            $this->assertStringContainsString('Básico', $errors['quota'][0]);
            $this->assertStringContainsString('octubre de 2026', $errors['quota'][0]);
        }
    }

    public function test_record_incrementa_el_contador_atomicamente(): void
    {
        $brand = $this->brandConPlan([]);
        $quota = app(QuotaGuard::class);

        $quota->record($brand, QuotaAction::ImageRefreshes, 2026, 10);
        $quota->record($brand, QuotaAction::ImageRefreshes, 2026, 10, 2);

        $this->assertSame(3, $brand->usageFor(2026, 10)->image_refreshes);
        $this->assertSame(3, $quota->used($brand, QuotaAction::ImageRefreshes, 2026, 10));
    }

    public function test_allows_network_segun_el_plan(): void
    {
        $brand = $this->brandConPlan(['networks' => ['linkedin']]);
        $quota = app(QuotaGuard::class);

        $this->assertTrue($quota->allowsNetwork($brand, Network::LinkedIn));
        $this->assertFalse($quota->allowsNetwork($brand, Network::Instagram));
        $this->assertFalse($quota->allowsNetwork($brand, Network::TikTok));

        // Todas las redes en el plan = todas permitidas
        $all = $this->brandConPlan(['networks' => ['instagram', 'linkedin', 'tiktok']]);
        $this->assertTrue($quota->allowsNetwork($all, Network::TikTok));
    }

    public function test_summary_devuelve_acciones_con_extras(): void
    {
        $brand = $this->brandConPlan(['name' => 'Pro']);
        $brand->usageFor(2026, 10)->update([
            'posts_generated' => 3,
            'extra_posts' => 2,
        ]);

        $summary = app(QuotaGuard::class)->summary($brand, 2026, 10);

        $this->assertSame('Pro', $summary['plan']);
        $this->assertFalse($summary['unlimited']);
        $posts = collect($summary['actions'])->firstWhere('action', 'posts_generated');
        $this->assertSame(3, $posts['used']);
        $this->assertSame(14, $posts['limit']);
        $this->assertSame(2, $posts['extra']);
    }
}
