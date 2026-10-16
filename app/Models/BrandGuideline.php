<?php

namespace App\Models;

use App\Enums\GuidelineSource;
use App\Enums\GuidelineStatus;
use App\Models\Concerns\BelongsToBrand;
use Database\Factories\BrandGuidelineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BrandGuideline extends Model
{
    /** @use HasFactory<BrandGuidelineFactory> */
    use BelongsToBrand, HasFactory;

    protected $fillable = [
        'brand_id',
        'text',
        'source',
        'status',
        'weight',
        'evidence_count',
        'period_from',
        'period_to',
    ];

    protected function casts(): array
    {
        return [
            'source' => GuidelineSource::class,
            'status' => GuidelineStatus::class,
            'period_from' => 'date:Y-m-d',
            'period_to' => 'date:Y-m-d',
        ];
    }
}
