<?php

namespace App\Enums;

enum QuotaAction: string
{
    case PostsGenerated = 'posts_generated';
    case Regenerations = 'regenerations';
    case ImageRefreshes = 'image_refreshes';

    public function planColumn(): string
    {
        return match ($this) {
            self::PostsGenerated => 'posts_per_month',
            self::Regenerations => 'regenerations_per_month',
            self::ImageRefreshes => 'image_refreshes_per_month',
        };
    }

    public function extraColumn(): string
    {
        return match ($this) {
            self::PostsGenerated => 'extra_posts',
            self::Regenerations => 'extra_regenerations',
            self::ImageRefreshes => 'extra_image_refreshes',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PostsGenerated => 'posts',
            self::Regenerations => 'regeneraciones',
            self::ImageRefreshes => 'fondos nuevos',
        };
    }
}
