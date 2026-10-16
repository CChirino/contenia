<?php

namespace App\Enums;

enum GuidelineStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case PendingRetire = 'pending_retire';
    case Retired = 'retired';
    case Discarded = 'discarded';
}
