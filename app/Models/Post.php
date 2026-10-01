<?php

namespace App\Models;

use App\Enums\Network;
use App\Enums\PostStatus;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    protected $fillable = [
        'monthly_plan_id',
        'scheduled_at',
        'network',
        'content_pillar_id',
        'format',
        'hook',
        'copy',
        'hashtags',
        'design_payload',
        'background_prompt',
        'template',
        'asset_path',
        'external_id',
        'permalink',
        'status',
        'error',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'network' => Network::class,
            'hashtags' => 'array',
            'design_payload' => 'array',
            'status' => PostStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function monthlyPlan(): BelongsTo
    {
        return $this->belongsTo(MonthlyPlan::class);
    }

    public function contentPillar(): BelongsTo
    {
        return $this->belongsTo(ContentPillar::class);
    }
}
