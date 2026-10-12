<?php

namespace App\Services\Images;

/**
 * Normaliza bytes de imagen (JPEG/PNG/etc.) a PNG con dimensiones exactas,
 * reescalando tipo "cover" y recortando el sobrante.
 */
trait CoverCrop
{
    /**
     * @throws ImageGenerationException
     */
    protected function coverCropPng(string $bytes, int $width, int $height): string
    {
        $source = @imagecreatefromstring($bytes);

        if ($source === false) {
            throw new ImageGenerationException('No se pudo decodificar la imagen.');
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        $canvas = imagecreatetruecolor($width, $height);
        $scale = max($width / $sourceWidth, $height / $sourceHeight);
        $scaledWidth = (int) ceil($sourceWidth * $scale);
        $scaledHeight = (int) ceil($sourceHeight * $scale);

        imagecopyresampled(
            $canvas, $source,
            (int) (($width - $scaledWidth) / 2), (int) (($height - $scaledHeight) / 2),
            0, 0,
            $scaledWidth, $scaledHeight,
            $sourceWidth, $sourceHeight
        );

        ob_start();
        imagepng($canvas);
        $png = ob_get_clean();

        imagedestroy($canvas);
        imagedestroy($source);

        return $png;
    }
}
