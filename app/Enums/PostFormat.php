<?php

namespace App\Enums;

enum PostFormat: string
{
    case Image = 'image';
    case Carousel = 'carousel';
    case Reel = 'reel';
    case Video = 'video';

    /**
     * Formatos permitidos por red (spec §4, v1).
     *
     * @return array<int, self>
     */
    public static function allowedFor(Network $network): array
    {
        return match ($network) {
            Network::Instagram => [self::Image, self::Carousel, self::Reel],
            Network::LinkedIn => [self::Image],
            Network::TikTok => [self::Video],
        };
    }
}
