<?php

namespace App\Services\Render;

use App\Enums\Network;
use App\Enums\PostFormat;
use InvalidArgumentException;

class TemplateRegistry
{
    /**
     * @return array<int, Template>
     */
    protected static function all(): array
    {
        return [
            new Template('instagram-carousel', Network::Instagram, PostFormat::Carousel, 1080, 1350, 'Instagram/CarouselSlide', true),
            new Template('instagram-quote', Network::Instagram, PostFormat::Image, 1080, 1350, 'Instagram/Quote', true),
            new Template('instagram-reel', Network::Instagram, PostFormat::Reel, 1080, 1920, 'Instagram/ReelFrame', true),
            new Template('linkedin-single', Network::LinkedIn, PostFormat::Image, 1200, 1200, 'LinkedIn/SingleImage', true),
            new Template('tiktok-video', Network::TikTok, PostFormat::Video, 1080, 1920, 'TikTok/VideoFrame', true),
        ];
    }

    public static function for(Network $network, PostFormat $format): Template
    {
        foreach (static::all() as $template) {
            if ($template->network === $network && $template->format === $format) {
                return $template;
            }
        }

        throw new InvalidArgumentException(
            "No hay plantilla para {$network->value}/{$format->value}."
        );
    }
}
