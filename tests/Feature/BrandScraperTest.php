<?php

namespace Tests\Feature;

use App\Services\Onboarding\BrandScraper;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BrandScraperTest extends TestCase
{
    private function homeHtml(array $extraLinks = []): string
    {
        $links = implode('', array_map(
            fn ($href) => "<a href=\"{$href}\">Ir</a>",
            array_merge(['/nosotros', '/contacto'], $extraLinks)
        ));

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <title>Marca Test</title>
            <meta name="description" content="Desc de la marca">
            <style>
                .cta { background: #3B82F6; color: #FFFFFF; }
                .badge { color: #3B82F6; }
                .footer { color: #999999; }
            </style>
            <script>var secreto = 'texto-del-script';</script>
        </head>
        <body style="border-color:#10B981">
            <h1>Bienvenidos</h1>
            <p>Texto visible de la home.</p>
            {$links}
            <a href="https://otro-dominio.com/x">Externo</a>
        </body>
        </html>
        HTML;
    }

    private function fakeSite(array $extraLinks = []): void
    {
        $page = '<html><body><p>Texto de una página interior.</p></body></html>';

        Http::fake([
            'https://marca.test' => Http::response($this->homeHtml($extraLinks)),
            'https://marca.test/*' => Http::response($page),
        ]);
    }

    public function test_collects_title_meta_text_and_prioritized_pages(): void
    {
        $this->fakeSite(['/blog']);

        $result = app(BrandScraper::class)->collect('https://marca.test');

        $this->assertTrue($result['ok']);
        $this->assertSame('Marca Test', $result['title']);
        $this->assertSame('Desc de la marca', $result['meta_description']);
        $this->assertStringContainsString('Texto visible de la home.', $result['home_text']);
        $this->assertStringNotContainsString('texto-del-script', $result['home_text']);

        // Los enlaces prioritarios (nosotros, blog) van antes que /contacto.
        $urls = array_column($result['pages'], 'url');
        $this->assertSame('https://marca.test/nosotros', $urls[0]);
        $this->assertContains('https://marca.test/blog', $urls);
        $this->assertContains('https://marca.test/contacto', $urls);
        $this->assertStringContainsString('página interior', $result['pages'][0]['text']);
    }

    public function test_extracts_saturated_hex_colors_and_filters_neutrals(): void
    {
        $this->fakeSite();

        $result = app(BrandScraper::class)->collect('https://marca.test');

        $this->assertContains('#3B82F6', $result['colors']); // <style>, dos usos
        $this->assertContains('#10B981', $result['colors']); // style="" inline
        $this->assertNotContains('#FFFFFF', $result['colors']); // luz > 0.95
        $this->assertNotContains('#999999', $result['colors']); // saturación < 0.15
    }

    public function test_fallen_site_returns_ok_false_without_throwing(): void
    {
        Http::fake(fn () => Http::response('caído', 500));

        $result = app(BrandScraper::class)->collect('https://caida.test');

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['error']);

        Http::fake(fn () => throw new ConnectionException('sin red'));

        $result = app(BrandScraper::class)->collect('https://caida.test');

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['error']);
    }

    public function test_does_not_follow_external_hosts(): void
    {
        $this->fakeSite();

        app(BrandScraper::class)->collect('https://marca.test');

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'otro-dominio.com'));
    }

    public function test_fetches_at_most_five_internal_pages(): void
    {
        $this->fakeSite(['/p1', '/p2', '/p3', '/p4', '/p5', '/p6', '/p7']);

        $result = app(BrandScraper::class)->collect('https://marca.test');

        $this->assertCount(5, $result['pages']);
        Http::assertSentCount(6); // home + 5 páginas
    }

    public function test_logo_colors_via_gd(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD no está disponible.');
        }

        // Logo PNG 16×16 rojo saturado embebido como data URI.
        $img = imagecreatetruecolor(16, 16);
        imagefilledrectangle($img, 0, 0, 15, 15, imagecolorallocate($img, 200, 40, 40));
        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        $dataUri = 'data:image/png;base64,'.base64_encode($png);

        $page = '<html><body><p>Interior</p></body></html>';
        $home = str_replace(
            '</body>',
            "<img src=\"{$dataUri}\" alt=\"logo de la marca\"></body>",
            $this->homeHtml()
        );

        Http::fake([
            'https://marca.test' => Http::response($home),
            'https://marca.test/*' => Http::response($page),
        ]);

        $result = app(BrandScraper::class)->collect('https://marca.test');

        $this->assertTrue($result['ok']);
        $this->assertSame($dataUri, $result['logo_img_url']);
        // Rojo (200,40,40) cuantizado a pasos de 32 → #C02020
        $this->assertContains('#C02020', $result['colors']);
    }
}
