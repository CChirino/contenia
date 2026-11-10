<?php

namespace Tests\Feature;

use App\Jobs\OnboardBrand;
use App\Models\Brand;
use App\Models\LlmLog;
use App\Models\Plan;
use App\Services\Llm\LlmClient;
use App\Services\Onboarding\SocialProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Modo "perfil social" del onboarding: marcas sin web cuya presencia
 * principal es Instagram/Facebook (legibles) o TikTok/LinkedIn (no).
 */
class SocialProfileTest extends TestCase
{
    use RefreshDatabase;

    private const IG_URL = 'https://www.instagram.com/sr.auto.ccs/';

    private const IMG_URL = 'https://scontent.cdninstagram.com/v/perfil.jpg?stp=x&_nc_cat=107';

    /** HTML real de Instagram (og meta con entidades HTML). */
    private function instagramHtml(): string
    {
        return '<html><head>'
            .'<meta property="og:image" content="https://scontent.cdninstagram.com/v/perfil.jpg?stp=x&amp;_nc_cat=107" />'
            .'<meta property="og:title" content="Sr.auto.ccs - Taller mec&#xe1;nico en Caracas a domicilio (&#064;sr.auto.ccs) &#x2022; Instagram photos and videos" />'
            .'<meta property="og:description" content="14K Followers, 133 Following, 197 Posts - See Instagram photos and videos from Sr.auto.ccs - Taller mec&#xe1;nico en Caracas a domicilio (&#064;sr.auto.ccs)" />'
            .'<style>a{color:#E1306C}</style></head><body><a href="/accounts/login/">Log in</a></body></html>';
    }

    /** PNG de un color sólido para los colores de la foto de perfil. */
    private function solidPng(int $r, int $g, int $b): string
    {
        $img = imagecreatetruecolor(40, 40);
        imagefill($img, 0, 0, imagecolorallocate($img, $r, $g, $b));
        ob_start();
        imagepng($img);
        $png = (string) ob_get_clean();
        imagedestroy($img);

        return $png;
    }

    private function fakeInstagram(): void
    {
        Http::fake([
            'https://www.instagram.com/*' => Http::response($this->instagramHtml()),
            'https://scontent.cdninstagram.com/*' => Http::response($this->solidPng(192, 32, 32)),
        ]);
    }

    public function test_detect_reconoce_las_cuatro_redes_y_handles(): void
    {
        $this->assertSame('instagram', SocialProfile::detect(self::IG_URL)['network']);
        $this->assertSame('sr.auto.ccs', SocialProfile::detect('instagram.com/sr.auto.ccs')['handle']);
        $this->assertSame('facebook', SocialProfile::detect('https://m.facebook.com/cocacola')['network']);
        $this->assertSame('facebook', SocialProfile::detect('https://fb.com/cocacola')['network']);
        $this->assertSame('tiktok', SocialProfile::detect('https://www.tiktok.com/@marca')['network']);
        $this->assertSame('marca', SocialProfile::detect('https://www.tiktok.com/@marca')['handle']);
        $this->assertSame('linkedin', SocialProfile::detect('https://www.linkedin.com/company/acme/')['network']);

        // Sin web: el usuario social decide (`@x` → Instagram, o URL completa).
        $this->assertSame(
            'https://www.instagram.com/sr.auto.ccs/',
            SocialProfile::detect(null, '@sr.auto.ccs')['url']
        );
        $this->assertSame('tiktok', SocialProfile::detect('', 'https://tiktok.com/@marca')['network']);

        // Web normal o nada → flujo de siempre.
        $this->assertNull(SocialProfile::detect('https://marca.test'));
        $this->assertNull(SocialProfile::detect('https://marca.test', '@marca'));
        $this->assertNull(SocialProfile::detect(null, null));
        $this->assertNull(SocialProfile::detect('', '   '));
    }

    public function test_lee_perfil_de_instagram_con_ua_simple_y_colores_de_la_foto(): void
    {
        $this->fakeInstagram();

        $data = app(SocialProfile::class)->read(SocialProfile::detect(self::IG_URL));

        $this->assertTrue($data['ok']);
        $this->assertSame('social', $data['source']);
        $this->assertSame('instagram', $data['network']);
        $this->assertSame('Sr.auto.ccs - Taller mecánico en Caracas a domicilio (@sr.auto.ccs)', $data['title']);
        $this->assertStringContainsString('Taller mecánico en Caracas', $data['meta_description']);
        $this->assertSame('14K Followers, 133 Following, 197 Posts', $data['stats']);
        $this->assertSame(self::IMG_URL, $data['profile_image_url']);
        $this->assertSame([], $data['pages']);

        // Colores solo de la foto de perfil (rojo), nunca del CSS de Instagram.
        $this->assertSame(['#C02020'], $data['colors']);
        $this->assertNotContains('#E1306C', $data['colors']);

        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://www.instagram.com/')
            && $r->header('User-Agent') === ['Mozilla/5.0']);
        // Perfil + foto: sin páginas internas ni login.
        Http::assertSentCount(2);
    }

    public function test_instagram_sin_meta_devuelve_error_de_captions(): void
    {
        Http::fake(['*' => Http::response('<html><head><title>Instagram</title></head></html>')]);

        $data = app(SocialProfile::class)->read(SocialProfile::detect(self::IG_URL));

        $this->assertFalse($data['ok']);
        $this->assertStringContainsString('No pudimos leer el perfil de Instagram', $data['error']);
        $this->assertStringContainsString('captions', $data['error']);
    }

    public function test_tiktok_y_linkedin_no_se_intentan_leer(): void
    {
        Http::fake();

        $tiktok = app(SocialProfile::class)->read(SocialProfile::detect('https://www.tiktok.com/@marca'));
        $linkedin = app(SocialProfile::class)->read(SocialProfile::detect('https://www.linkedin.com/company/acme'));

        $this->assertFalse($tiktok['ok']);
        $this->assertStringContainsString('TikTok', $tiktok['error']);
        $this->assertFalse($linkedin['ok']);
        $this->assertStringContainsString('LinkedIn', $linkedin['error']);
        Http::assertNothingSent();
    }

    private function socialBrand(string $website): Brand
    {
        return Brand::factory()->create([
            'website_url' => $website,
            'plan_id' => Plan::factory()->create(['networks' => ['instagram']]),
        ]);
    }

    private function llmResponse(): array
    {
        return [
            'description' => 'Taller mecánico a domicilio en Caracas.',
            'tone_of_voice' => 'Cercano',
            'audience' => 'Conductores en Caracas',
            'cta' => 'Escríbenos',
            'base_hashtags' => ['#taller'],
            'pillars' => [
                ['name' => 'A', 'description' => 'd', 'weight' => 25],
                ['name' => 'B', 'description' => 'd', 'weight' => 25],
                ['name' => 'C', 'description' => 'd', 'weight' => 25],
                ['name' => 'D', 'description' => 'd', 'weight' => 25],
            ],
            'strategies' => [['network' => 'instagram', 'posts_per_week' => 3, 'formats' => ['image'], 'focus' => 'x']],
            'palette' => ['dark' => '#111111', 'light' => '#EEEEEE', 'accent' => '#C02020', 'secondary' => '#224466'],
        ];
    }

    public function test_onboard_brand_con_instagram_usa_el_perfil_y_propone_logo(): void
    {
        $this->fakeInstagram();
        $brand = $this->socialBrand(self::IG_URL);
        app(LlmClient::class)->withResponse($this->llmResponse());

        OnboardBrand::dispatchSync($brand, []);

        $prompt = LlmLog::withoutGlobalScopes()->latest('id')->value('prompt');
        $this->assertStringContainsString('su presencia principal es Instagram', $prompt);
        $this->assertStringContainsString('Taller mecánico en Caracas', $prompt);
        $this->assertStringNotContainsString('Título de la home', $prompt);

        $proposal = $brand->fresh()->onboarding_proposal;
        $this->assertArrayNotHasKey('error', $proposal);
        $this->assertSame(self::IMG_URL, $proposal['logo_candidate_url']);
    }

    public function test_onboard_brand_con_tiktok_sin_posts_no_llama_al_llm(): void
    {
        Http::fake();
        $brand = $this->socialBrand('https://www.tiktok.com/@marca');

        OnboardBrand::dispatchSync($brand, ['social_handle' => '@marca']);

        $this->assertStringContainsString('captions', $brand->fresh()->onboarding_proposal['error']);
        $this->assertSame(0, LlmLog::withoutGlobalScopes()->count());
    }

    public function test_onboard_brand_con_tiktok_y_posts_llama_al_llm(): void
    {
        Http::fake();
        $brand = $this->socialBrand('https://www.tiktok.com/@marca');
        app(LlmClient::class)->withResponse($this->llmResponse());

        OnboardBrand::dispatchSync($brand, ['posts' => ['Cambio de aceite a domicilio 🚗']]);

        $prompt = LlmLog::withoutGlobalScopes()->latest('id')->value('prompt');
        $this->assertStringContainsString('su presencia principal es TikTok', $prompt);
        $this->assertStringContainsString('Cambio de aceite a domicilio', $prompt);
        $this->assertArrayNotHasKey('error', $brand->fresh()->onboarding_proposal);
    }

    public function test_marca_sin_web_puede_proponer_con_solo_usuario_social(): void
    {
        Queue::fake();
        $brand = $this->socialBrand('');
        $brand->forceFill(['website_url' => null])->save();
        $this->actingAsAdminFor();

        $this->post(route('brands.onboarding.run', $brand), ['social_handle' => '@sr.auto.ccs'])
            ->assertSessionHasNoErrors();

        $this->post(route('brands.onboarding.run', $brand), [])
            ->assertSessionHasErrors('sample_posts');
    }

    public function test_usar_foto_de_perfil_como_logo(): void
    {
        Storage::fake('local');
        Http::fake(['https://scontent.cdninstagram.com/*' => Http::response($this->solidPng(10, 20, 30))]);
        $brand = $this->socialBrand(self::IG_URL);
        $brand->forceFill(['onboarding_proposal' => ['logo_candidate_url' => self::IMG_URL]])->save();
        $this->actingAsAdminFor();

        // Una URL en el body se ignora: siempre se usa la de la propuesta.
        $this->post(route('brands.onboarding.apply.logo', $brand), ['url' => 'https://evil.test/x.png'])
            ->assertSessionHasNoErrors();

        $brand->refresh();
        $this->assertSame("brands/{$brand->id}/logo.png", $brand->logo_path);
        Storage::disk('local')->assertExists($brand->logo_path);
        $this->assertTrue($brand->onboarding_proposal['applied']['logo']);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'evil.test'));
    }

    public function test_usar_logo_sin_candidato_devuelve_422(): void
    {
        $brand = $this->socialBrand(self::IG_URL);
        $this->actingAsAdminFor();

        $this->post(route('brands.onboarding.apply.logo', $brand))->assertStatus(422);
    }

    public function test_gestor_ajeno_no_puede_usar_el_logo(): void
    {
        $brand = $this->socialBrand(self::IG_URL);
        $brand->forceFill(['onboarding_proposal' => ['logo_candidate_url' => self::IMG_URL]])->save();
        $this->actingAsManagerFor(Brand::factory()->create());

        $this->post(route('brands.onboarding.apply.logo', $brand))->assertForbidden();
    }
}
