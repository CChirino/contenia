<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class FfmpegMediaInspector implements MediaInspector
{
    /**
     * Metadatos: getimagesize para imágenes, ffprobe para video.
     */
    public function probe(string $absPath): array
    {
        $mime = (string) mime_content_type($absPath);
        $size = (int) filesize($absPath);

        if (str_starts_with($mime, 'image/')) {
            $info = @getimagesize($absPath);

            throw_unless(
                is_array($info),
                RuntimeException::class,
                'El archivo no es una imagen válida.'
            );

            return [
                'width' => (int) $info[0],
                'height' => (int) $info[1],
                'duration' => null,
                'size' => $size,
                'mime' => $mime,
            ];
        }

        // Video (u otro contenedor): ffprobe devuelve JSON con streams.
        $result = Process::timeout(60)->run([
            'ffprobe', '-v', 'error', '-print_format', 'json',
            '-show_streams', '-show_format', $absPath,
        ]);

        throw_unless(
            $result->successful(),
            RuntimeException::class,
            'ffprobe no pudo leer el archivo: '.trim($result->errorOutput())
        );

        $json = json_decode($result->output(), true) ?? [];
        $video = collect($json['streams'] ?? [])->firstWhere('codec_type', 'video');

        throw_unless(
            is_array($video),
            RuntimeException::class,
            'El archivo no contiene una pista de video.'
        );

        return [
            'width' => (int) ($video['width'] ?? 0),
            'height' => (int) ($video['height'] ?? 0),
            'duration' => isset($json['format']['duration'])
                ? (float) $json['format']['duration']
                : (isset($video['duration']) ? (float) $video['duration'] : null),
            'size' => $size,
            'mime' => $mime,
        ];
    }

    /**
     * Foto: 1 JPEG <=1024px de lado largo. Video: 3 fotogramas al
     * 5/50/90 % de la duración, <=768px, JPEG.
     */
    public function framesAsDataUrls(string $absPath, string $mime): array
    {
        if (str_starts_with($mime, 'image/')) {
            $jpeg = MediaNormalizer::fitJpeg(
                $absPath,
                (int) config('media.vision_image_max_side', 1024)
            );

            return ['data:image/jpeg;base64,'.base64_encode($jpeg)];
        }

        $probe = $this->probe($absPath);
        $duration = (float) ($probe['duration'] ?? 1.0);
        $maxSide = (int) config('media.vision_frame_max_side', 768);
        $urls = [];

        foreach ([0.05, 0.5, 0.9] as $ratio) {
            $tmp = tempnam(sys_get_temp_dir(), 'frame').'.jpg';
            $at = $this->num($duration * $ratio);

            $result = Process::timeout(60)->run([
                'ffmpeg', '-y', '-ss', $at, '-i', $absPath,
                '-frames:v', '1', '-q:v', '4',
                '-vf', "scale={$maxSide}:{$maxSide}:force_original_aspect_ratio=decrease",
                $tmp,
            ]);

            throw_unless(
                $result->successful() && is_file($tmp),
                RuntimeException::class,
                'ffmpeg no pudo extraer el fotograma: '.trim($result->errorOutput())
            );

            $urls[] = 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($tmp));
            @unlink($tmp);
        }

        return $urls;
    }

    /**
     * Extrae un fotograma como PNG (póster de videos y fondo de la
     * portada en modo plantilla). Vive en el inspector, no en el
     * assembler, por ser análisis del material.
     */
    public function extractPoster(string $absPath, string $outPng, int $maxSide): void
    {
        $probe = $this->probe($absPath);
        $at = $this->num(max(0.05, (float) ($probe['duration'] ?? 1.0) * 0.05));

        $result = Process::timeout(60)->run([
            'ffmpeg', '-y', '-ss', $at, '-i', $absPath,
            '-frames:v', '1',
            '-vf', "scale={$maxSide}:{$maxSide}:force_original_aspect_ratio=decrease",
            $outPng,
        ]);

        throw_unless(
            $result->successful() && is_file($outPng),
            RuntimeException::class,
            'ffmpeg no pudo extraer el póster: '.trim($result->errorOutput())
        );
    }

    private function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');
    }
}
