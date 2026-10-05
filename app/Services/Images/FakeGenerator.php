<?php

namespace App\Services\Images;

class FakeGenerator implements ImageGenerator
{
    /** @var array<int, string|ImageGenerationException> */
    protected array $responses = [];

    public function name(): string
    {
        return 'fake';
    }

    public function withImage(string $bytes): static
    {
        $this->responses[] = $bytes;

        return $this;
    }

    public function withException(string $message = 'Fallo simulado de imagen.'): static
    {
        $this->responses[] = new ImageGenerationException($message);

        return $this;
    }

    public function generate(string $prompt, int $width, int $height, int $seed): string
    {
        $result = array_shift($this->responses);

        if ($result instanceof ImageGenerationException) {
            throw $result;
        }

        if (is_string($result)) {
            return $result;
        }

        // PNG 1x1 transparente.
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );
    }
}
