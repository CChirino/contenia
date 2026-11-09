<?php

namespace App\Services\Render;

interface VideoAssembler
{
    /**
     * Une los frames en un MP4 (xfade + pista de audio).
     *
     * @param  array<int, string>  $framePaths  Rutas absolutas de los PNG.
     *
     * @throws RenderException
     */
    public function assemble(array $framePaths, string $outputPath, int $width, int $height): void;

    /**
     * Video "con marca" (post desde archivo, modo plantilla): portada
     * renderizada 2 s + xfade + video subido escalado con pad a w×h,
     * logo PNG opcional en la esquina inferior derecha (12 % del ancho,
     * alfa 0.9). Se conserva el audio del video original si lo tiene.
     *
     * @throws RenderException
     */
    public function assembleWithSource(string $coverPath, string $sourcePath, ?string $logoPath, string $outputPath, int $width, int $height): void;

    /**
     * Video "tal cual": normaliza a h264/yuv420p + aac + faststart sin
     * reescalar.
     *
     * @throws RenderException
     */
    public function normalize(string $sourcePath, string $outputPath): void;
}
