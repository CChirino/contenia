<?php

namespace App\Enums;

enum PostStatus: string
{
    case Drafted = 'drafted';
    case Approved = 'approved';
    case Rendered = 'rendered';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Failed = 'failed';

    /**
     * Grafo de transiciones válidas (spec §4).
     */
    public function canTransitionTo(self $status): bool
    {
        return in_array($status, match ($this) {
            self::Drafted => [self::Approved],
            self::Approved => [self::Rendered],
            self::Rendered => [self::Scheduled, self::Approved],
            self::Scheduled => [self::Published, self::Failed],
            self::Failed => [self::Scheduled],
            self::Published => [],
        }, true);
    }
}
