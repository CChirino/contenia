<?php

namespace App\Models;

use Database\Factories\ReviewWeekCommentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewWeekComment extends Model
{
    /** @use HasFactory<ReviewWeekCommentFactory> */
    use HasFactory;

    protected $fillable = [
        'review_request_id',
        'week_from',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'week_from' => 'date:Y-m-d',
        ];
    }

    public function reviewRequest(): BelongsTo
    {
        return $this->belongsTo(ReviewRequest::class);
    }
}
