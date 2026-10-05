<?php

namespace Tests\Feature;

use App\Services\Render\FfmpegVideoAssembler;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Test real de ffmpeg: 3 PNG de GD → MP4 con video h264 + audio.
 * Excluido por defecto (grupo "render"); corre con --group=render.
 */
#[Group('render')]
class VideoAssemblerRealTest extends TestCase
{
    public function test_assembles_mp4_with_video_and_audio(): void
    {
        $dir = sys_get_temp_dir().'/va-test-'.uniqid();
        mkdir($dir, 0755, true);

        try {
            $frames = [];

            foreach ([0x1A2A42, 0x27C2A5, 0x7854D2] as $i => $hex) {
                $img = imagecreatetruecolor(1080, 1920);
                $color = imagecolorallocate($img, ($hex >> 16) & 0xFF, ($hex >> 8) & 0xFF, $hex & 0xFF);
                imagefill($img, 0, 0, $color);
                $path = "{$dir}/frame-0".($i + 1).'.png';
                imagepng($img, $path);
                imagedestroy($img);
                $frames[] = $path;
            }

            $out = "{$dir}/final.mp4";
            (new FfmpegVideoAssembler)->assemble($frames, $out, 1080, 1920);

            $this->assertFileExists($out);
            $this->assertGreaterThan(10000, filesize($out));

            $probe = Process::run([
                'ffprobe', '-v', 'quiet', '-print_format', 'json',
                '-show_streams', '-show_format', $out,
            ]);
            $this->assertTrue($probe->successful(), $probe->errorOutput());
            $info = json_decode($probe->output(), true);

            $video = collect($info['streams'])->firstWhere('codec_type', 'video');
            $audio = collect($info['streams'])->firstWhere('codec_type', 'audio');

            $this->assertNotNull($video, 'Sin stream de video');
            $this->assertSame('h264', $video['codec_name']);
            $this->assertSame(1080, (int) $video['width']);
            $this->assertSame(1920, (int) $video['height']);
            $this->assertNotNull($audio, 'Sin stream de audio');

            // 3 frames × 3.5 s − 2 × 0.5 s = 9.5 s (±0.3)
            $duration = (float) $info['format']['duration'];
            $this->assertEqualsWithDelta(9.5, $duration, 0.3);
        } finally {
            array_map('unlink', glob("{$dir}/*") ?: []);
            rmdir($dir);
        }
    }
}
