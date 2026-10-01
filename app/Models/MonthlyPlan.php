<?php

namespace App\Models;

use Database\Factories\MonthlyPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonthlyPlan extends Model
{
    /** @use HasFactory<MonthlyPlanFactory> */
    use HasFactory;

    protected $fillable = [
        'year',
        'month',
        'status',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
        ];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
