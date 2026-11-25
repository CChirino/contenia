<?php

namespace Tests\Unit;

use App\Support\BrandPalette;
use PHPUnit\Framework\TestCase;

class BrandPaletteTest extends TestCase
{
    public function test_normalize_nueva(): void
    {
        $p = BrandPalette::normalize(['primary' => ['#771339', '#bf8660', '#fffcf3'], 'secondary' => ['#2B2B2B']]);
        $this->assertSame(['#771339', '#BF8660', '#FFFCF3'], $p['primary']);
        $this->assertSame(['#2B2B2B'], $p['secondary']);
    }

    public function test_normalize_antigua(): void
    {
        $p = BrandPalette::normalize([
            'dark' => '#1A2A42',
            'light' => '#F8F5F0',
            'accent' => '#27C2A5',
            'secondary' => '#7854D2',
        ]);
        $this->assertSame(['#27C2A5', '#7854D2', '#1A2A42'], $p['primary']);
        $this->assertSame(['#F8F5F0'], $p['secondary']);
    }

    public function test_normalize_basura_no_lanza(): void
    {
        $p = BrandPalette::normalize('basura');
        $this->assertCount(3, $p['primary']);
        foreach ($p['primary'] as $c) {
            $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $c);
        }
    }

    /**
     * Casos fijos compartidos con el test TS (tests/fixtures/palette_cases.json).
     *
     * @dataProvider casosFijos
     */
    public function test_roles_casos_fijos(array $in, array $expected): void
    {
        $roles = BrandPalette::roles($in);
        $this->assertSame($expected['accent'], $roles['accent'], 'accent');
        $this->assertSame($expected['dark'], $roles['dark'], 'dark');
        $this->assertSame($expected['light'], $roles['light'], 'light');
        $this->assertSame($expected['secondary'], $roles['secondary'], 'secondary');
    }

    public static function casosFijos(): array
    {
        $cases = json_decode(
            file_get_contents(__DIR__.'/../fixtures/palette_cases.json'),
            true
        );

        // Clave = nombre del caso (aparece en el informe de PHPUnit).
        return collect($cases)
            ->mapWithKeys(fn ($c) => [$c['name'] => [$c['in'], $c['roles']]])
            ->all();
    }

    public function test_contraste_dark_light_siempre_4_5(): void
    {
        foreach (self::casosFijos() as $name => [$in]) {
            $roles = BrandPalette::roles($in);
            $this->assertGreaterThanOrEqual(
                4.5,
                BrandPalette::contrast($roles['dark'], $roles['light']),
                "contraste dark/light en {$name}"
            );
            $this->assertGreaterThanOrEqual(
                4.5,
                BrandPalette::contrast($roles['dark'], '#FFFFFF'),
                "contraste dark/blanco en {$name}"
            );
        }
    }
}
