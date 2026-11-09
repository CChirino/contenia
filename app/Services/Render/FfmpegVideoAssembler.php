<?php

namespace App\Services\Render;

use Illuminate\Support\Facades\Process;

class FfmpegVideoAssembler implements VideoAssembler
{
    public function assemble(array $framePaths, string $outputPath, int $width, int $height): void
    {
        $count = count($framePaths);
        $spf = (float) config('render.seconds_per_frame', 3.5);
        $fade = (float) config('render.transition_seconds', 0.5);
        $duration = $count * $spf - max(0, $count - 1) * $fade;

        $args = [config('render.ffmpeg_binary', 'ffmpeg'), '-y'];

        foreach ($framePaths as $path) {
            $args[] = '-loop';
            $args[] = '1';
            $args[] = '-t';
            $args[] = $this->num($spf);
            $args[] = '-i';
            $args[] = $path;
        }

        $args[] = '-stream_loop';
        $args[] = '-1';
        $args[] = '-i';
        $args[] = config('render.audio_track');

        $filters = [];
        foreach ($framePaths as $i => $path) {
            $filters[] = "[{$i}:v]scale={$width}:{$height}:force_original_aspect_ratio=increase,crop={$width}:{$height},setsar=1,fps=30[v{$i}]";
        }

        $videoLabel = 'v0';
        for ($i = 1; $i < $count; $i++) {
            $offset = $i * $spf - ($i - 1) * $fade;
            $out = "x{$i}";
            $filters[] = "[{$videoLabel}][v{$i}]xfade=transition=fade:duration={$this->num($fade)}:offset={$this->num($offset)}[{$out}]";
            $videoLabel = $out;
        }

        $fadeOutStart = max(0, $duration - 1.5);
        $filters[] = "[{$count}:a]atrim=duration={$this->num($duration)},volume=0.6,afade=t=out:st={$this->num($fadeOutStart)}:d=1.5[aout]";

        $args[] = '-filter_complex';
        $args[] = implode(';', $filters);
        $args[] = '-map';
        $args[] = "[{$videoLabel}]";
        $args[] = '-map';
        $args[] = '[aout]';

        $args = array_merge($args, [
            '-c:v', 'libx264',
            '-preset', 'medium',
            '-crf', '20',
            '-pix_fmt', 'yuv420p',
            '-r', '30',
            '-movflags', '+faststart',
            '-c:a', 'aac',
            '-b:a', '128k',
            '-shortest',
            $outputPath,
        ]);

        $result = Process::timeout(300)->run($args);

        if (! $result->successful()) {
            $stderr = trim($result->errorOutput());
            $tail = implode("\n", array_slice(explode("\n", $stderr), -20));

            throw new RenderException("ffmpeg falló: {$tail}");
        }
    }

    /**
     * Portada renderizada (2 s) + xfade 0.5 s + video del operador con
     * pad a w×h; logo PNG en la esquina inferior derecha si se indica.
     * El audio sale del video original (si no tiene pista, va sin audio).
     */
    public function assembleWithSource(string $coverPath, string $sourcePath, ?string $logoPath, string $outputPath, int $width, int $height): void
    {
        $args = [config('render.ffmpeg_binary', 'ffmpeg'), '-y'];

        // 0: portada como video de 2.5 s (2 s visibles + 0.5 de transición).
        $args = array_merge($args, ['-loop', '1', '-t', '2.5', '-i', $coverPath]);
        // 1: video subido por el operador.
        $args = array_merge($args, ['-i', $sourcePath]);
        // 2: el mismo archivo SOLO para el audio: el video entra tras la
        // portada (xfade offset 2 s), así que su audio se desplaza 2 s con
        // -itsoffset; mapear 1:a lo dejaría 2 s adelantado.
        $args = array_merge($args, ['-itsoffset', '2', '-i', $sourcePath]);

        $filters = [
            "[0:v]scale={$width}:{$height}:force_original_aspect_ratio=increase,crop={$width}:{$height},setsar=1,fps=30[vcover]",
            "[1:v]scale={$width}:{$height}:force_original_aspect_ratio=decrease,pad={$width}:{$height}:(ow-iw)/2:(oh-ih)/2,setsar=1,fps=30[vsrc]",
            '[vcover][vsrc]xfade=transition=fade:duration=0.5:offset=2[vx]',
        ];

        $videoLabel = 'vx';

        // Logo PNG en la esquina inferior derecha (12 % del ancho, alfa
        // 0.9). Los SVG no se pasan como $logoPath.
        if ($logoPath !== null) {
            // 3: logo (los índices 0-2 son portada, video y audio desplazado).
            $args = array_merge($args, ['-i', $logoPath]);
            $filters[] = "[3:v]scale={$width}*0.12:-1,format=rgba,colorchannelmixer=aa=0.9[vlogo]";
            $filters[] = "[{$videoLabel}][vlogo]overlay=main_w-overlay_w-24:main_h-overlay_h-24[vout]";
            $videoLabel = 'vout';
        }

        $args[] = '-filter_complex';
        $args[] = implode(';', $filters);
        $args[] = '-map';
        $args[] = "[{$videoLabel}]";
        // Audio del video original desde el input desplazado (alineado
        // con su imagen tras la portada); opcional si el clip es mudo.
        $args[] = '-map';
        $args[] = '2:a?';

        $args = array_merge($args, [
            '-c:v', 'libx264',
            '-preset', 'medium',
            '-crf', '20',
            '-pix_fmt', 'yuv420p',
            '-r', '30',
            '-movflags', '+faststart',
            '-c:a', 'aac',
            '-b:a', '128k',
            '-shortest',
            $outputPath,
        ]);

        $result = Process::timeout(300)->run($args);

        if (! $result->successful()) {
            $stderr = trim($result->errorOutput());
            $tail = implode("\n", array_slice(explode("\n", $stderr), -20));

            throw new RenderException("ffmpeg falló en assembleWithSource: {$tail}");
        }
    }

    /**
     * Normaliza el video subido a h264/yuv420p + aac + faststart sin
     * reescalar (modo "tal cual").
     */
    public function normalize(string $sourcePath, string $outputPath): void
    {
        $result = Process::timeout(300)->run([
            config('render.ffmpeg_binary', 'ffmpeg'), '-y',
            '-i', $sourcePath,
            '-c:v', 'libx264',
            '-preset', 'medium',
            '-crf', '20',
            '-pix_fmt', 'yuv420p',
            '-movflags', '+faststart',
            '-c:a', 'aac',
            '-b:a', '128k',
            $outputPath,
        ]);

        if (! $result->successful()) {
            $stderr = trim($result->errorOutput());
            $tail = implode("\n", array_slice(explode("\n", $stderr), -20));

            throw new RenderException("ffmpeg falló al normalizar el video: {$tail}");
        }
    }

    private function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }
}
