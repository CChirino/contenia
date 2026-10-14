<?php

namespace App\Services\Render;

use App\Models\Post;
use Illuminate\Support\Facades\Storage;

class FakeFrameRenderer implements FrameRenderer
{
    protected ?RenderException $exception = null;

    public function withException(string $message = 'Fallo simulado de render.'): static
    {
        $this->exception = new RenderException($message);

        return $this;
    }

    public function render(Post $post): array
    {
        if ($this->exception) {
            throw $this->exception;
        }

        $frames = TemplateRegistry::frameCount($post);
        $names = [];

        for ($i = 1; $i <= $frames; $i++) {
            $name = sprintf('frame-%02d.png', $i);
            Storage::disk('local')->put($post->assetPath($name), $this->tinyPng());
            $names[] = $name;
        }

        return $names;
    }

    private function tinyPng(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );
    }
}
