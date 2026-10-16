<?php

namespace App\Listeners;

use App\Events\ReviewSubmitted;
use App\Jobs\DistillFeedback;

/**
 * Al cerrar una revisión del cliente, destila su feedback en la memoria
 * de la marca (spec §8.2). Autodescubierto por el tipo del argumento.
 */
class OnReviewSubmitted
{
    public function handle(ReviewSubmitted $event): void
    {
        DistillFeedback::dispatch(
            $event->review->brand,
            $event->review->from_date,
            $event->review->to_date,
        );
    }
}
