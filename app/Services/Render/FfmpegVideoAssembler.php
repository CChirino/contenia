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

    private function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }
}
