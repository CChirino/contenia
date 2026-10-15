<?php

namespace App\Events;

use App\Models\ReviewRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * El cliente envió su revisión. Sin listeners por ahora (DistillFeedback,
 * memoria IA, es Fase 4 del roadmap multi-marca).
 */
class ReviewSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(public ReviewRequest $review) {}
}
