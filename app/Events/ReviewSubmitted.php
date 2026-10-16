<?php

namespace App\Events;

use App\Models\ReviewRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * El cliente envió su revisión. El listener OnReviewSubmitted encola
 * DistillFeedback para destilar el feedback en la memoria de la marca.
 */
class ReviewSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(public ReviewRequest $review) {}
}
