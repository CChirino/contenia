<?php

namespace App\Services\Images;

interface ImageGenerator
{
    /**
     * Genera una imagen y devuelve sus bytes (PNG/JPEG).
     *
     * @throws ImageGenerationException
     */
    public function generate(string $prompt, int $width, int $height, int $seed): string;

    public function name(): string;
}
