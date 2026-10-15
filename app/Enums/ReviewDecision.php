<?php

namespace App\Enums;

enum ReviewDecision: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
