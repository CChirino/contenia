<?php

namespace App\Models;

use App\Enums\ReviewDecision;
use Database\Factories\ReviewItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewItem extends Model
{
    /** @use HasFactory<ReviewItemFactory> */
    use HasFactory;

    protected $fillable = [
        'review_request_id',
        'post_id',
        'decision',
        'comment',
        'week_from',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decision' => ReviewDecision::class,
            'week_from' => 'date:Y-m-d',
            'decided_at' => 'datetime',
        ];
    }

    public function reviewRequest(): BelongsTo
    {
        return $this->belongsTo(ReviewRequest::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
