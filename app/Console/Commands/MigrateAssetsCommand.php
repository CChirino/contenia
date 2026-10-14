<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Migra los assets del layout plano storage/app/assets/{post_id} al layout por
 * marca storage/app/brands/{brand_id}/posts/{post_id}. Idempotente: los
 * directorios ya migrados se omiten; posts sin contraparte en BD se avisan.
 */
class MigrateAssetsCommand extends Command
{
    protected $signature = 'brands:migrate-assets';

    protected $description = 'Mueve storage/app/assets/{post} → brands/{brand_id}/posts/{post_id} y reescribe posts.asset_path';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $dirs = $disk->directories('assets');

        if (empty($dirs)) {
            $this->info('No hay directorios en assets/ — nada que migrar.');

            return self::SUCCESS;
        }

        $moved = 0;
        $skipped = 0;

        foreach ($dirs as $dir) {
            $postId = (int) basename($dir);
            $post = Post::withoutGlobalScopes()->find($postId);

            if (! $post) {
                $this->warn("  {$dir}: post {$postId} no existe, se deja intacto.");
                $skipped++;

                continue;
            }

            $target = $post->assetDir();

            foreach ($disk->files($dir) as $file) {
                $name = basename($file);
                $to = "{$target}/{$name}";

                if (! $disk->exists($to)) {
                    $disk->makeDirectory($target);
                    $disk->move($file, $to);
                } else {
                    // Ya migrado (idempotente): borra el origen duplicado.
                    $disk->delete($file);
                }
            }

            if (empty($disk->files($dir)) && empty($disk->directories($dir))) {
                $disk->deleteDirectory($dir);
            }

            // posts.asset_path guarda la ruta relativa: reescribirla.
            if ($post->asset_path && str_starts_with($post->asset_path, 'assets/')) {
                $post->forceFill(['asset_path' => $target.'/'.basename($post->asset_path)])->save();
            }

            $this->line("  {$dir} → {$target}");
            $moved++;
        }

        $this->info("Migrados: {$moved}, omitidos: {$skipped}.");

        return self::SUCCESS;
    }
}
