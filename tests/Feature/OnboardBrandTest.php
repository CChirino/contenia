<?php

namespace Tests\Feature;

use App\Enums\Network;
use App\Enums\PostFormat;
use App\Jobs\OnboardBrand;
use App\Models\Brand;
use App\Models\Plan;
use App\Services\Llm\LlmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OnboardBrandTest extends TestCase
{
    use RefreshDatabase;

    private function brand(array $networks = ['instagram', 'linkedin'], ?string $website = 'https://marca.test'): Brand
    {
        return Brand::factory()->create([
            'website_url' => $website,
            'plan_id' => Plan::factory()->create(['networks' => $networks]),
        ]);
    }

    private function fakeSite(): void
    {
        Http::fake([
            'https://marca.test*' => Http::response(
                '<html><head><title>Marca</title><meta name="description" content="Desc">'
                .'<style>a{color:#3B82F6}</style></head><body><p>Hola mundo</p></body></html>'
            ),
        ]);
    }

    private function llmResponse(): array
    {
        return [
            'description' => 'Marca de prueba para el onboarding.',
            'tone_of_voice' => 'Cercano y directo.',
            'audience' => 'Pymes hispanohablantes.',
            'cta' => 'Escríbenos',
            'base_hashtags' => array_merge(['uno', '#dos'], array_map(fn ($i) => "#tag{$i}", range(3, 9))),
            'pillars' => [
                ['name' => 'P1', 'description' => 'd', 'weight' => 1],
                ['name' => 'P2', 'description' => 'd', 'weight' => 2],
                ['name' => 'P3', 'description' => 'd', 'weight' => 3],
                ['name' => 'P4', 'description' => 'd', 'weight' => 4],
                ['name' => 'P5', 'description' => 'd', 'weight' => 5],
                ['name' => 'P6', 'description' => 'd', 'weight' => 6],
                ['name' => 'P7-sobra', 'description' => 'd', 'weight' => 99],
            ],
            'strategies' => [
                [
                    'network' => 'instagram',
                    'posts_per_week' => 20,
                    'formats' => ['image', 'video', 'inventado'],
                    'focus' => 'Reels de producto',
                ],
                [
                    'network' => 'tiktok', // fuera del plan → ignorada
                    'posts_per_week' => 3,
                    'formats' => ['video'],
                    'focus' => 'x',
                ],
            ],
            'palette' => [
                'dark' => '1a2a42',
                'light' => '#f8f5f0',
                'accent' => 'no-es-hex',
                'secondary' => '#27c2a5',
            ],
        ];
    }

    public function test_produces_normalized_proposal_without_touching_profile(): void
    {
        $this->fakeSite();
        $brand = $this->brand();
        $brand->profile()->create([
            'brand_id' => $brand->id,
            'description' => 'Descripción puesta a mano',
            'tone_of_voice' => '',
            'audience' => '',
            'base_hashtags' => [],
            'cta' => '',
            'url' => '',
            'palette' => [],
        ]);

        Cache::put("onboarding:{$brand->id}", true, 600);
        app(LlmClient::class)->withResponse($this->llmResponse());

        OnboardBrand::dispatchSync($brand, ['posts' => ['Post de muestra']]);

        $proposal = $brand->fresh()->onboarding_proposal;

        $this->assertIsArray($proposal);
        $this->assertArrayNotHasKey('error', $proposal);
        $this->assertNotEmpty($proposal['generated_at']);
        $this->assertSame('Marca de prueba para el onboarding.', $proposal['description']);

        // Pilares: máx. 6, pesos renormalizados a 100 (largest-remainder).
        $this->assertGreaterThanOrEqual(4, count($proposal['pillars']));
        $this->assertLessThanOrEqual(6, count($proposal['pillars']));
        $this->assertSame(100, array_sum(array_column($proposal['pillars'], 'weight')));
        $this->assertNotContains('P7-sobra', array_column($proposal['pillars'], 'name'));

        // Estrategias: solo redes del plan, frecuencia 1–7, formatos válidos.
        $networks = array_column($proposal['strategies'], 'network');
        $this->assertEqualsCanonicalizing(['instagram', 'linkedin'], $networks);
        $instagram = collect($proposal['strategies'])->firstWhere('network', 'instagram');
        $this->assertSame(7, $instagram['posts_per_week']); // clamp de 20
        $this->assertSame(['image'], $instagram['formats']); // video/inventado fuera
        $linkedin = collect($proposal['strategies'])->firstWhere('network', 'linkedin');
        $this->assertSame(
            array_map(fn (PostFormat $f) => $f->value, PostFormat::allowedFor(Network::LinkedIn)),
            $linkedin['formats']
        );

        // Paleta saneada a #RRGGBB (con fallback para valores inválidos).
        foreach (['dark', 'light', 'accent', 'secondary'] as $key) {
            $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $proposal['palette'][$key]);
        }
        $this->assertSame('#1A2A42', $proposal['palette']['dark']);
        $this->assertSame('#F8F5F0', $proposal['palette']['light']);

        // Hashtags con # y ≤8.
        $this->assertLessThanOrEqual(8, count($proposal['base_hashtags']));
        foreach ($proposal['base_hashtags'] as $tag) {
            $this->assertStringStartsWith('#', $tag);
        }

        // Nunca pisa el perfil ni datos reales de la marca.
        $this->assertSame('Descripción puesta a mano', $brand->profile()->first()->description);

        // La flag de "generando" se libera al terminar.
        $this->assertFalse(Cache::has("onboarding:{$brand->id}"));
    }

    public function test_binds_the_job_brand_in_the_container(): void
    {
        $this->fakeSite();
        $brand = $this->brand();
        $this->assertNotSame($brand->id, app('brand')->id); // Saberé activa

        app(LlmClient::class)->withResponse($this->llmResponse());
        OnboardBrand::dispatchSync($brand, []);

        $this->assertSame($brand->id, app('brand')->id);
    }

    public function test_failed_scrape_without_samples_stores_error_proposal(): void
    {
        Http::fake(fn () => Http::response('caído', 500));
        $brand = $this->brand();

        OnboardBrand::dispatchSync($brand, []);

        $proposal = $brand->fresh()->onboarding_proposal;
        $this->assertNotEmpty($proposal['error']);
        $this->assertNotEmpty($proposal['generated_at']);
        $this->assertFalse(Cache::has("onboarding:{$brand->id}"));
    }

    public function test_brand_without_website_and_samples_stores_error(): void
    {
        $brand = $this->brand(website: null);

        OnboardBrand::dispatchSync($brand, []);

        $this->assertNotEmpty($brand->fresh()->onboarding_proposal['error']);
    }

    public function test_llm_failure_stores_error_proposal(): void
    {
        $this->fakeSite();
        $brand = $this->brand();

        app(LlmClient::class)->withException('quota agotada');
        OnboardBrand::dispatchSync($brand, []);

        $proposal = $brand->fresh()->onboarding_proposal;
        $this->assertStringContainsString('quota agotada', $proposal['error']);
    }
}
