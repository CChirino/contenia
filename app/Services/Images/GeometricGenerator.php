<?php

namespace App\Services\Images;

/**
 * Fondo geométrico de marca con GD: siempre funciona, determinista por seed.
 */
class GeometricGenerator implements ImageGenerator
{
    public function __construct(protected string $variant = 'dark') {}

    public function name(): string
    {
        return 'geometric';
    }

    public function generate(string $prompt, int $width, int $height, int $seed): string
    {
        $image = imagecreatetruecolor($width, $height);

        if (! $image) {
            throw new ImageGenerationException('No se pudo crear el lienzo GD.');
        }

        imagealphablending($image, true);

        [$br, $bg, $bb] = $this->variant === 'light'
            ? [0xF8, 0xF5, 0xF0]   // sabere.light
            : [0x1A, 0x2A, 0x42];  // sabere.dark

        imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, $br, $bg, $bb));

        mt_srand($seed);

        $palette = [[0x27, 0xC2, 0xA5], [0x78, 0x54, 0xD2]]; // accent, purple
        if ($this->variant === 'light') {
            $palette[] = [0x1A, 0x2A, 0x42];
        }

        $shapes = 3 + mt_rand(0, 2);

        for ($i = 0; $i < $shapes; $i++) {
            [$r, $g, $b] = $palette[$i % count($palette)];
            $alpha = mt_rand(15, 45); // 0 opaco … 127 transparente
            $color = imagecolorallocatealpha($image, $r, $g, $b, 40 + $alpha);

            $cx = mt_rand(0, $width);
            $cy = mt_rand(0, $height);
            $radius = (int) (min($width, $height) * (0.15 + mt_rand(0, 30) / 100));

            if (mt_rand(0, 1) === 0) {
                imagefilledellipse($image, $cx, $cy, $radius * 2, $radius * 2, $color);
            } else {
                $points = [];
                for ($v = 0; $v < 6; $v++) {
                    $angle = deg2rad(60 * $v - 30);
                    $points[] = $cx + $radius * cos($angle);
                    $points[] = $cy + $radius * sin($angle);
                }
                imagefilledpolygon($image, $points, $color);
            }
        }

        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}
