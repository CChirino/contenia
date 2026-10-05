<?php

namespace App\Services\Images;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class PollinationsGenerator implements ImageGenerator
{
    public function name(): string
    {
        return 'pollinations';
    }

    public function generate(string $prompt, int $width, int $height, int $seed): string
    {
        $response = Http::timeout(120)
            ->retry(
                2,
                fn (int $attempt) => $attempt * 5000,
                fn ($exception, $request) => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException
                        && ($exception->response->status() === 429 || $exception->response->serverError())),
                throw: false
            )
            ->withHeaders([
                'Authorization' => 'Bearer '.config('images.pollinations.api_key'),
            ])
            ->get($this->endpoint().rawurlencode($prompt), [
                'model' => config('images.pollinations.model'),
                'width' => $width,
                'height' => $height,
                'seed' => $seed,
            ]);

        if (! $response->successful()) {
            $message = $response->json('error.message') ?? substr($response->body(), 0, 300);

            throw new ImageGenerationException(
                'Error HTTP '.$response->status().' de Pollinations: '.$message
            );
        }

        $bytes = $response->body();

        if ($bytes === '' || ! @getimagesizefromstring($bytes)) {
            throw new ImageGenerationException('Pollinations devolvió una respuesta que no es una imagen válida.');
        }

        return $this->normalizeToPng($bytes, $width, $height);
    }

    private function endpoint(): string
    {
        return rtrim(config('images.pollinations.base_url') ?? 'https://gen.pollinations.ai', '/').'/image/';
    }

    /**
     * Pollinations puede devolver JPEG y dimensiones distintas de las pedidas;
     * se normaliza a PNG con las dimensiones exactas (recorte tipo "cover").
     */
    private function normalizeToPng(string $bytes, int $width, int $height): string
    {
        $source = @imagecreatefromstring($bytes);

        if ($source === false) {
            throw new ImageGenerationException('No se pudo decodificar la imagen de Pollinations.');
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        $canvas = imagecreatetruecolor($width, $height);
        $scale = max($width / $sourceWidth, $height / $sourceHeight);
        $scaledWidth = (int) ceil($sourceWidth * $scale);
        $scaledHeight = (int) ceil($sourceHeight * $scale);

        imagecopyresampled(
            $canvas, $source,
            (int) (($width - $scaledWidth) / 2), (int) (($height - $scaledHeight) / 2),
            0, 0,
            $scaledWidth, $scaledHeight,
            $sourceWidth, $sourceHeight
        );

        ob_start();
        imagepng($canvas);
        $png = ob_get_clean();

        imagedestroy($canvas);
        imagedestroy($source);

        return $png;
    }
}
