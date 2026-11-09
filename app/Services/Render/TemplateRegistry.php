<?php

namespace App\Services\Render;

use App\Enums\MediaMode;
use App\Enums\MediaSource;
use App\Enums\Network;
use App\Enums\PostFormat;
use App\Models\Post;
use InvalidArgumentException;

class TemplateRegistry
{
    /**
     * @return array<int, Template>
     */
    protected static function all(): array
    {
        return [
            new Template('instagram-carousel', Network::Instagram, PostFormat::Carousel, 1080, 1350, 743, 'Instagram/CarouselSlide', true),
            new Template('instagram-quote', Network::Instagram, PostFormat::Image, 1080, 1350, 743, 'Instagram/Quote', true),
            new Template('instagram-reel', Network::Instagram, PostFormat::Reel, 1080, 1920, 998, 'Instagram/ReelFrame', true),
            new Template('linkedin-single', Network::LinkedIn, PostFormat::Image, 1200, 1200, 660, 'LinkedIn/SingleImage', true),
            new Template('tiktok-video', Network::TikTok, PostFormat::Video, 1080, 1920, 998, 'TikTok/VideoFrame', true),
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

    /**
     * Número de frames que produce un post: image=1; carousel = slides + 1
     * (CTA final), mín 3 máx 7; reel/video = escenas, mín 2 máx 5.
     */
    public static function frameCount(Post $post): int
    {
        // Video subido en modo plantilla: solo se renderiza la portada.
        if ($post->media_source === MediaSource::Upload
            && $post->media_mode === MediaMode::Template
            && in_array($post->format, [PostFormat::Reel, PostFormat::Video], true)) {
            return 1;
        }

        $slides = count($post->design_payload['slides'] ?? []);

        return match ($post->format) {
            PostFormat::Image => 1,
            PostFormat::Carousel => max(3, min(7, $slides + 1)),
            PostFormat::Reel, PostFormat::Video => max(2, min(5, $slides)),
        };
    }
}
