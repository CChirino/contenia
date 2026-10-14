<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBrand;
use Database\Factories\MonthlyPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonthlyPlan extends Model
{
    /** @use HasFactory<MonthlyPlanFactory> */
    use BelongsToBrand, HasFactory;

    protected $fillable = [
        'brand_id',
        'year',
        'month',
        'status',
        'generated_at',
        'last_error',
        'generating_at',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'generating_at' => 'datetime',
            'last_error' => 'array',
        ];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
