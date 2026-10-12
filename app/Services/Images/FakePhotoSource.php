<?php

namespace App\Services\Images;

class FakePhotoSource implements PhotoSource
{
    use CoverCrop;

    protected ?PhotoResult $next = null;

    protected bool $returnNull = false;

    /** @var array<int, array{keywords: string, skip: int}> */
    public array $calls = [];

    public function withResult(?string $png = null, array $meta = []): static
    {
        $this->next = new PhotoResult(
            bytes: $png ?? $this->tinyPng(),
            meta: $meta ?: ['source' => 'pixabay', 'id' => 1, 'page_url' => 'https://pixabay.com/fake', 'user' => 'fake', 'tags' => 'school, desk', 'skip' => 0],
        );

        return $this;
    }

    public function withNull(): static
    {
        $this->returnNull = true;
        $this->next = null;

        return $this;
    }

    public function find(string $keywords, int $width, int $height, int $seed, int $skip = 0): ?PhotoResult
    {
        $this->calls[] = ['keywords' => $keywords, 'skip' => $skip];

        if ($this->returnNull) {
            return null;
        }

        return $this->next ?? new PhotoResult(
            bytes: $this->tinyPng(),
            meta: ['source' => 'pixabay', 'id' => 1, 'page_url' => 'https://pixabay.com/fake', 'user' => 'fake', 'tags' => 'school, desk', 'skip' => $skip],
        );
    }

    protected function tinyPng(): string
    {
        $img = imagecreatetruecolor(8, 8);
        ob_start();
        imagepng($img);

        return ob_get_clean();
    }
}
