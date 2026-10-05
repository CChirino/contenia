<?php

namespace Tests\Feature;

use App\Enums\Network;
use App\Enums\PostFormat;
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
}
