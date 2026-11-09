<?php

namespace App\Services\Media;

use RuntimeException;

/**
 * Normalización GD de imágenes subidas por el operador: misma mecánica
 * de cover/fit que la normalización de fondos IA (CoverCrop).
 */
class MediaNormalizer
{
    /**
     * PNG a tamaño exacto tipo cover (rellena recortando), como bg.png.
     */
    public static function coverPng(string $sourceAbsPath, int $width, int $height): string
    {
        $source = self::decode($sourceAbsPath);

        $sw = imagesx($source);
        $sh = imagesy($source);
        $canvas = imagecreatetruecolor($width, $height);
        $scale = max($width / $sw, $height / $sh);
        $scaledW = (int) ceil($sw * $scale);
        $scaledH = (int) ceil($sh * $scale);

        imagecopyresampled(
            $canvas, $source,
            (int) (($width - $scaledW) / 2), (int) (($height - $scaledH) / 2),
            0, 0, $scaledW, $scaledH, $sw, $sh
        );

        $png = self::output(fn () => imagepng($canvas));

        imagedestroy($canvas);
        imagedestroy($source);

        return $png;
    }

    /**
     * PNG ajustado sin recorte: el lado largo no supera $maxSide.
     */
    public static function fitPng(string $sourceAbsPath, int $maxSide): string
    {
        $source = self::decode($sourceAbsPath);

        $sw = imagesx($source);
        $sh = imagesy($source);
        $scale = min(1, $maxSide / max($sw, $sh));
        $dw = max(1, (int) round($sw * $scale));
        $dh = max(1, (int) round($sh * $scale));

        $canvas = imagecreatetruecolor($dw, $dh);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $dw, $dh, $sw, $sh);

        $png = self::output(fn () => imagepng($canvas));

        imagedestroy($canvas);
        imagedestroy($source);

        return $png;
    }

    /**
     * JPEG ajustado sin recorte (para enviar al modelo de visión).
     */
    public static function fitJpeg(string $sourceAbsPath, int $maxSide, int $quality = 85): string
    {
        $source = self::decode($sourceAbsPath);

        $sw = imagesx($source);
        $sh = imagesy($source);
        $scale = min(1, $maxSide / max($sw, $sh));
        $dw = max(1, (int) round($sw * $scale));
        $dh = max(1, (int) round($sh * $scale));

        $canvas = imagecreatetruecolor($dw, $dh);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $dw, $dh, $sw, $sh);

        $jpeg = self::output(fn () => imagejpeg($canvas, null, $quality));

        imagedestroy($canvas);
        imagedestroy($source);

        return $jpeg;
    }

    public static function decode(string $absPath): \GdImage
    {
        $bytes = @file_get_contents($absPath);
        $image = $bytes === false ? false : @imagecreatefromstring($bytes);

        if ($image === false) {
            throw new RuntimeException("No se pudo decodificar la imagen: {$absPath}");
        }

        return $image;
    }

    private static function output(callable $fn): string
    {
        ob_start();
        $fn();
        $data = ob_get_clean();

        if ($data === false || $data === '') {
            throw new RuntimeException('No se pudo codificar la imagen normalizada.');
        }

        return $data;
    }
}
