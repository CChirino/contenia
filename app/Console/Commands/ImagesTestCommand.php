<?php

namespace App\Console\Commands;

use App\Services\Images\ImageGenerationException;
use App\Services\Images\ImageGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImagesTestCommand extends Command
{
    protected $signature = 'images:test {width=1200} {height=1200} {--prompt=}';

    protected $description = 'Genera una imagen de prueba con el driver configurado y la guarda en storage/app/test-bg.png';

    public function handle(ImageGenerator $generator): int
    {
        $width = (int) $this->argument('width');
        $height = (int) $this->argument('height');
        $prompt = $this->option('prompt')
            ?: 'oficina escolar moderna con pupitres y pizarra'.', '.config('images.style_suffix');

        $this->info('Driver: '.$generator->name()." ({$width}x{$height})");

        try {
            $bytes = $generator->generate($prompt, $width, $height, 42);
        } catch (Throwable $e) {
            $this->error($e instanceof ImageGenerationException ? $e->getMessage() : (string) $e);

            return self::FAILURE;
        }

        Storage::disk('local')->put('test-bg.png', $bytes);

        $info = @getimagesizefromstring($bytes);

        $this->info(sprintf(
            'Guardado en storage/app/test-bg.png — %d bytes, %sx%s (%s)',
            strlen($bytes),
            $info[0] ?? '?',
            $info[1] ?? '?',
            $info['mime'] ?? '?'
        ));

        return self::SUCCESS;
    }
}
