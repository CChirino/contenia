<?php

namespace App\Services\Images;

use App\Models\Post;
use App\Services\Render\TemplateRegistry;
use Illuminate\Support\Facades\Storage;

class BackgroundService
{
    /**
     * Devuelve la ruta absoluta del fondo del post (storage/app/assets/{id}/bg.png).
     * Cachea el archivo; con $fresh regenera. Si el driver falla usa el
     * fallback geométrico y marca background_source en el post.
     */
    public function for(Post $post, bool $fresh = false): string
    {
        $relative = "assets/{$post->id}/bg.png";
        $absolute = Storage::disk('local')->path($relative);

        if (! $fresh && is_file($absolute)) {
            return $absolute;
        }

        $template = TemplateRegistry::for($post->network, $post->format);

        $prompt = 'no text, no letters, no signs, no people, no faces. '
            .($post->background_prompt ?: $post->hook)
            .', '.config('images.style_suffix');

        $source = config('images.driver');

        try {
            $bytes = app(ImageGenerator::class)->generate(
                $prompt, $template->width, $template->height, $post->id
            );
        } catch (ImageGenerationException $e) {
            $fallback = config('images.fallback', 'geometric');
            $bytes = $this->generator($fallback)->generate(
                $prompt, $template->width, $template->height, $post->id
            );
            $source = $fallback;
        }

        Storage::disk('local')->put($relative, $bytes);

        $post->forceFill(['background_source' => $source])->save();

        return $absolute;
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
