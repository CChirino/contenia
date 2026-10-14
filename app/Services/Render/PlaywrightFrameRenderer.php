<?php

namespace App\Services\Render;

use App\Models\Post;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class PlaywrightFrameRenderer implements FrameRenderer
{
    public function render(Post $post): array
    {
        $template = TemplateRegistry::for($post->network, $post->format);
        $frames = TemplateRegistry::frameCount($post);
        $outDir = Storage::disk('local')->path("assets/{$post->id}");

        // La firma cubre la URL completa (incluido el frame), así que se
        // genera una URL firmada por frame y se pasan vía --urls-file.
        URL::forceRootUrl(config('render.internal_url'));
        $urls = collect(range(1, $frames))
            ->map(fn (int $i) => URL::temporarySignedRoute('render.frame', now()->addMinutes(10), [
                'brand' => $post->brand->slug,
                'post' => $post->id,
                'frame' => $i,
            ]))
            ->all();
        URL::forceRootUrl(config('app.url'));

        if (! is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }
        $urlsFile = $outDir.'/urls.json';
        file_put_contents($urlsFile, json_encode($urls));

        $result = Process::timeout(300)->run([
            config('render.node_binary'),
            config('render.script'),
            '--urls-file', $urlsFile,
            '--out-dir', $outDir,
            '--width', (string) $template->width,
            '--height', (string) $template->height,
            '--timeout', '60000',
        ]);

        if (! $result->successful()) {
            throw new RenderException(
                'Falló el render de frames: '.trim($result->errorOutput() ?: $result->output())
            );
        }

        $names = [];

        for ($i = 1; $i <= $frames; $i++) {
            $name = sprintf('frame-%02d.png', $i);

            if (! Storage::disk('local')->exists("assets/{$post->id}/{$name}")) {
                throw new RenderException("El render no produjo {$name}.");
            }

            $names[] = $name;
        }

        return $names;
    }
}
