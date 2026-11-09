<?php

namespace App\Services\Media;

class FakeMediaInspector implements MediaInspector
{
    /** @var array<int, string> */
    public array $probeCalls = [];

    /** @var array<int, array{path: string, mime: string}> */
    public array $frameCalls = [];

    /** @var array<int, array{path: string, out: string}> */
    public array $posterCalls = [];

    /** Metadatos que devuelve probe(); configurable en tests. */
    public array $meta = [
        'width' => 1080,
        'height' => 1350,
        'duration' => null,
        'size' => 1024,
        'mime' => 'image/jpeg',
    ];

    public function withMeta(array $meta): static
    {
        $this->meta = $meta + $this->meta;

        return $this;
    }

    public function probe(string $absPath): array
    {
        $this->probeCalls[] = $absPath;
        $meta = $this->meta;
        $meta['size'] = is_file($absPath) ? (int) filesize($absPath) : $meta['size'];

        return $meta;
    }

    public function framesAsDataUrls(string $absPath, string $mime): array
    {
        $this->frameCalls[] = ['path' => $absPath, 'mime' => $mime];

        // JPEG de 1x1 válido para data URLs de prueba.
        $jpeg = base64_decode(
            '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAABAAEDASIAAhEBAxEB/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/xAAUAQEAAAAAAAAAAAAAAAAAAAAA/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAwDAQACEQMRAD8AfwA//9k='
        );

        $count = str_starts_with($mime, 'image/') ? 1 : 3;

        return array_fill(0, $count, 'data:image/jpeg;base64,'.base64_encode($jpeg));
    }

    public function extractPoster(string $absPath, string $outPng, int $maxSide): void
    {
        $this->posterCalls[] = ['path' => $absPath, 'out' => $outPng];

        file_put_contents($outPng, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));
    }
}
