<?php

namespace App\Models;

use Database\Factories\PostEditFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Diff de una edición manual a hook/copy. No lleva brand_id propio:
 * la marca se deduce del post (evidencia para DistillFeedback).
 */
class PostEdit extends Model
{
    /** @use HasFactory<PostEditFactory> */
    use HasFactory;

    protected $fillable = [
        'post_id',
        'user_id',
        'field',
        'before',
        'after',
        'review_request_id',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewRequest(): BelongsTo
    {
        return $this->belongsTo(ReviewRequest::class);
    }
}
