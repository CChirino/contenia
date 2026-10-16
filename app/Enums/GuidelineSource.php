<?php

namespace App\Enums;

enum GuidelineSource: string
{
    case AiDistilled = 'ai_distilled';
    case ClientFeedback = 'client_feedback';
    case Operator = 'operator';
}
