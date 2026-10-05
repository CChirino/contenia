<?php

namespace App\Services\Render;

class FakeVideoAssembler implements VideoAssembler
{
    /** @var array<int, array{frames: array<int, string>, output: string, width: int, height: int}> */
    public array $calls = [];

    protected ?RenderException $exception = null;

    public function withException(string $message = 'Fallo simulado de ffmpeg.'): static
    {
        $this->exception = new RenderException($message);

        return $this;
    }

    public function assemble(array $framePaths, string $outputPath, int $width, int $height): void
    {
        if ($this->exception) {
            throw $this->exception;
        }

        $this->calls[] = [
            'frames' => $framePaths,
            'output' => $outputPath,
            'width' => $width,
            'height' => $height,
        ];

        // MP4 mínimo (ftyp box) para que el archivo exista.
        file_put_contents($outputPath, "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42mp41");
    }
}
