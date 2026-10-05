<?php

namespace Tests\Feature;

use App\Services\Images\GeometricGenerator;
use Tests\TestCase;

class GeometricGeneratorTest extends TestCase
{
    public function test_generates_png_with_exact_dimensions(): void
    {
        $png = (new GeometricGenerator)->generate('cualquier prompt', 1200, 1200, 7);

        $info = getimagesizefromstring($png);

        $this->assertSame(1200, $info[0]);
        $this->assertSame(1200, $info[1]);
        $this->assertSame('image/png', $info['mime']);
    }

    public function test_supports_light_variant_and_is_deterministic(): void
    {
        $a = (new GeometricGenerator('light'))->generate('p', 1080, 1920, 5);
        $b = (new GeometricGenerator('light'))->generate('p', 1080, 1920, 5);

        $this->assertSame($a, $b);
        $this->assertSame([1080, 1920], [getimagesizefromstring($a)[0], getimagesizefromstring($a)[1]]);
    }
}
