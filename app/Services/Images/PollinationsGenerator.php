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

    use CoverCrop;

    public function generate(string $prompt, int $width, int $height, int $seed): string
    {
        // Algunos modelos (p. ej. microsoft/mai-image-2.6-flash) exigen
        // dimensiones múltiplos de 16; se piden redondeadas hacia arriba y
        // el resultado se recorta al tamaño exacto.
        $requestWidth = (int) (ceil($width / 16) * 16);
        $requestHeight = (int) (ceil($height / 16) * 16);

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
                'width' => $requestWidth,
                'height' => $requestHeight,
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

        return $this->coverCropPng($bytes, $width, $height);
    }

    private function endpoint(): string
    {
        return rtrim(config('images.pollinations.base_url') ?? 'https://gen.pollinations.ai', '/').'/image/';
    }
}
