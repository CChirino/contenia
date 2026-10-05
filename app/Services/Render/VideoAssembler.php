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
}
