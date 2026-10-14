<?php

namespace App\Services\Images;

use App\Models\Post;
use App\Services\Render\TemplateRegistry;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BackgroundService
{
    /**
     * Devuelve la ruta absoluta del fondo del post (storage/app/assets/{id}/bg.png),
     * de width×photoHeight según la plantilla. Cachea el archivo; con $fresh
     * regenera (en Pixabay avanza al siguiente candidato). Cadena de fuentes:
     * foto de stock → generador IA fotográfico → geométrico.
     */
    public function for(Post $post, bool $fresh = false): string
    {
        $relative = $post->assetPath('bg.png');
        $absolute = Storage::disk('local')->path($relative);

        if (! $fresh && is_file($absolute)) {
            return $absolute;
        }

        $template = TemplateRegistry::for($post->network, $post->format);
        $width = $template->width;
        $height = $template->photoHeight;

        $result = $this->fromPhotoSource($post, $width, $height, $fresh);

        if ($result === null) {
            $result = $this->fromImageGenerator($post, $width, $height);
        }

        Storage::disk('local')->put($relative, $result['bytes']);

        $post->forceFill([
            'background_source' => $result['source'],
            'background_meta' => $result['meta'],
        ])->save();

        return $absolute;
    }

    /**
     * @return array{bytes: string, source: string, meta: array<string, mixed>}|null
     */
    protected function fromPhotoSource(Post $post, int $width, int $height, bool $fresh): ?array
    {
        if (blank($post->photo_keywords)) {
            return null;
        }

        try {
            $skip = $fresh ? (int) ($post->background_meta['skip'] ?? 0) + 1 : 0;
            $photo = app(PhotoSource::class)->find(
                $post->photo_keywords, $width, $height, $post->id, $skip
            );
        } catch (Throwable) {
            return null;
        }

        if ($photo === null) {
            return null;
        }

        return ['bytes' => $photo->bytes, 'source' => 'pixabay', 'meta' => $photo->meta];
    }

    /**
     * @return array{bytes: string, source: string, meta: array<string, mixed>}
     */
    protected function fromImageGenerator(Post $post, int $width, int $height): array
    {
        $prompt = 'candid editorial photograph of '
            .($post->background_prompt ?: $post->hook)
            .', '.config('images.style_suffix');

        try {
            $bytes = app(ImageGenerator::class)->generate($prompt, $width, $height, $post->id);

            return [
                'bytes' => $bytes,
                'source' => config('images.driver'),
                'meta' => [
                    'source' => 'pollinations',
                    'model' => config('images.pollinations.model'),
                ],
            ];
        } catch (Throwable) {
            $fallback = config('images.fallback', 'geometric');
            $bytes = $this->generator($fallback)->generate($prompt, $width, $height, $post->id);

            return [
                'bytes' => $bytes,
                'source' => $fallback,
                'meta' => ['source' => 'geometric'],
            ];
        }
    }

    protected function generator(string $name): ImageGenerator
    {
        return match ($name) {
            'geometric' => new GeometricGenerator,
            'pollinations' => new PollinationsGenerator,
            'fake' => new FakeGenerator,
            default => throw new ImageGenerationException("Generador de imagen desconocido: {$name}"),
        };
    }
}
