<?php

namespace App\Services\Media;

interface MediaInspector
{
    /**
     * Metadatos del archivo subido.
     *
     * @return array{width: int, height: int, duration: float|null, size: int, mime: string}
     */
    public function probe(string $absPath): array;

    /**
     * Data URLs JPEG para el modelo de visión: 1 imagen para fotos
     * (lado largo <= vision_image_max_side), 3 fotogramas al 5/50/90 %
     * de la duración para videos (<= vision_frame_max_side).
     *
     * @return array<int, string>
     */
    public function framesAsDataUrls(string $absPath, string $mime): array;
}
