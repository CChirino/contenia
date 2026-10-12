<?php

namespace App\Services\Images;

interface PhotoSource
{
    /**
     * Busca una foto de stock para las keywords dadas y la devuelve
     * normalizada a PNG de width×height (cover-crop). Devuelve null si
     * no hay resultados utilizables.
     *
     * @throws ImageGenerationException ante error HTTP del proveedor
     */
    public function find(string $keywords, int $width, int $height, int $seed, int $skip = 0): ?PhotoResult;
}
