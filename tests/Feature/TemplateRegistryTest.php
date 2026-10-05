<?php

namespace Tests\Feature;

use App\Enums\Network;
use App\Enums\PostFormat;
use App\Models\Post;
use App\Services\Render\TemplateRegistry;
use InvalidArgumentException;
use Tests\TestCase;

class TemplateRegistryTest extends TestCase
{
    public function test_resolves_each_network_format_pair(): void
    {
        $cases = [
            [Network::Instagram, PostFormat::Carousel, 'Instagram/CarouselSlide', 1080, 1350],
            [Network::Instagram, PostFormat::Image, 'Instagram/Quote', 1080, 1350],
            [Network::Instagram, PostFormat::Reel, 'Instagram/ReelFrame', 1080, 1920],
            [Network::LinkedIn, PostFormat::Image, 'LinkedIn/SingleImage', 1200, 1200],
            [Network::TikTok, PostFormat::Video, 'TikTok/VideoFrame', 1080, 1920],
        ];

        foreach ($cases as [$network, $format, $component, $width, $height]) {
            $template = TemplateRegistry::for($network, $format);

            $this->assertSame($component, $template->component);
            $this->assertSame($width, $template->width);
            $this->assertSame($height, $template->height);
        }
    }

    public function test_throws_for_unsupported_combination(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TemplateRegistry::for(Network::LinkedIn, PostFormat::Reel);
    }

    public function test_frame_count_per_format(): void
    {
        $slides = fn (int $n) => ['slides' => array_fill(0, $n, ['title' => 't', 'body' => 'b'])];

        $image = Post::factory()->make([
            'network' => Network::LinkedIn,
            'format' => PostFormat::Image,
            'design_payload' => $slides(1),
        ]);
        $this->assertSame(1, TemplateRegistry::frameCount($image));

        $carousel = Post::factory()->make([
            'network' => Network::Instagram,
            'format' => PostFormat::Carousel,
            'design_payload' => $slides(4),
        ]);
        $this->assertSame(5, TemplateRegistry::frameCount($carousel)); // 4 slides + CTA

        $carouselMin = Post::factory()->make([
            'network' => Network::Instagram,
            'format' => PostFormat::Carousel,
            'design_payload' => $slides(1),
        ]);
        $this->assertSame(3, TemplateRegistry::frameCount($carouselMin));

        $carouselMax = Post::factory()->make([
            'network' => Network::Instagram,
            'format' => PostFormat::Carousel,
            'design_payload' => $slides(9),
        ]);
        $this->assertSame(7, TemplateRegistry::frameCount($carouselMax));

        $reel = Post::factory()->make([
            'network' => Network::Instagram,
            'format' => PostFormat::Reel,
            'design_payload' => $slides(3),
        ]);
        $this->assertSame(3, TemplateRegistry::frameCount($reel));

        $videoMin = Post::factory()->make([
            'network' => Network::TikTok,
            'format' => PostFormat::Video,
            'design_payload' => $slides(1),
        ]);
        $this->assertSame(2, TemplateRegistry::frameCount($videoMin));
    }
}
