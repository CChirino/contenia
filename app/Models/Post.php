<?php

namespace App\Models;

use App\Enums\Network;
use App\Enums\PostFormat;
use App\Enums\PostIcon;
use App\Enums\PostStatus;
use App\Models\Concerns\BelongsToBrand;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use BelongsToBrand, HasFactory;

    protected $fillable = [
        'brand_id',
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
        'photo_keywords',
        'icon',
        'background_source',
        'background_meta',
        'render_error',
        'rendered_at',
        'render_requested_at',
        'frames',
        'external_id',
        'permalink',
        'status',
        'error',
        'regeneration_error',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'network' => Network::class,
            'format' => PostFormat::class,
            'hashtags' => 'array',
            'design_payload' => 'array',
            'status' => PostStatus::class,
            'published_at' => 'datetime',
            'rendered_at' => 'datetime',
            'render_requested_at' => 'datetime',
            'frames' => 'array',
            'background_meta' => 'array',
            'icon' => PostIcon::class,
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
