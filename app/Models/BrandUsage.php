<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBrand;
use Database\Factories\BrandUsageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BrandUsage extends Model
{
    /** @use HasFactory<BrandUsageFactory> */
    use BelongsToBrand, HasFactory;

    protected $table = 'brand_usage';

    protected $fillable = [
        'brand_id',
        'year',
        'month',
        'posts_generated',
        'regenerations',
        'image_refreshes',
        'llm_tokens_in',
        'llm_tokens_out',
        'image_calls',
        'extra_posts',
        'extra_regenerations',
        'extra_image_refreshes',
    ];
}
