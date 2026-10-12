<?php

namespace App\Services\Images;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class PixabayPhotoSource implements PhotoSource
{
    use CoverCrop;

    public function find(string $keywords, int $width, int $height, int $seed, int $skip = 0): ?PhotoResult
    {
        $response = Http::timeout(20)
            ->retry(
                1,
                2000,
                fn ($exception, $request) => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException
                        && ($exception->response->status() === 429 || $exception->response->serverError())),
                throw: false
            )
            ->get('https://pixabay.com/api/', [
                'key' => config('images.pixabay.api_key'),
                'q' => $keywords,
                'image_type' => 'photo',
                'safesearch' => 'true',
                'orientation' => 'horizontal',
                'min_width' => 1280,
                'per_page' => 30,
                'order' => 'popular',
                'lang' => 'en',
            ]);

        if (! $response->successful()) {
            throw new ImageGenerationException(
                'Error HTTP '.$response->status().' de Pixabay: '.substr($response->body(), 0, 300)
            );
        }

        $hits = collect($response->json('hits') ?? [])
            ->reject(fn (array $hit) => $this->hasExcludedTag($hit['tags'] ?? ''))
            ->values();

        if ($hits->isEmpty()) {
            return null;
        }

        $hit = $hits->get(($seed + $skip) % $hits->count());

        $image = Http::timeout(30)->get($hit['largeImageURL'])->body();

        return new PhotoResult(
            bytes: $this->coverCropPng($image, $width, $height),
            meta: [
                'source' => 'pixabay',
                'id' => $hit['id'] ?? null,
                'page_url' => $hit['pageURL'] ?? null,
                'user' => $hit['user'] ?? null,
                'tags' => $hit['tags'] ?? null,
                'skip' => $skip,
            ],
        );
    }

    /**
     * Palabra completa, case-insensitive: descarta fotos con menores.
     */
    protected function hasExcludedTag(string $tags): bool
    {
        $excluded = config('images.pixabay.exclude_tags', []);

        foreach ($excluded as $tag) {
            if (preg_match('/\b'.preg_quote($tag, '/').'\b/i', $tags)) {
                return true;
            }
        }

        return false;
    }
}
