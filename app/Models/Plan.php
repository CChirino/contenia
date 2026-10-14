<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'posts_per_month',
        'regenerations_per_month',
        'image_refreshes_per_month',
        'networks',
        'price_label',
    ];

    protected function casts(): array
    {
        return [
            'networks' => 'array',
        ];
    }

    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class);
    }
}
