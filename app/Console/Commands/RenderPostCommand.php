<?php

namespace App\Console\Commands;

use App\Jobs\RenderPost;
use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;

class RenderPostCommand extends Command
{
    protected $signature = 'render:post {post : ID del post} {--fresh : Regenera el fondo ignorando la caché} {--force : (solo depuración) renderiza aunque el post no esté aprobado, sin cambiar su estado}';

    protected $description = 'Renderiza los frames PNG de un post de forma síncrona';

    public function handle(): int
    {
        $post = Post::find($this->argument('post'));

        if (! $post) {
            $this->error('Post no encontrado.');

            return self::FAILURE;
        }

        $start = microtime(true);

        App::call([new RenderPost($post, (bool) $this->option('fresh'), (bool) $this->option('force')), 'handle']);

        $post->refresh();
        $ms = (int) ((microtime(true) - $start) * 1000);

        if ($post->render_error) {
            $this->error('render_error: '.$post->render_error);

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Post %d → status=%s, frames=[%s], background_source=%s, %d ms',
            $post->id,
            $post->status->value,
            implode(', ', $post->frames ?? []),
            $post->background_source ?? 'n/a',
            $ms,
        ));

        return self::SUCCESS;
    }
}
