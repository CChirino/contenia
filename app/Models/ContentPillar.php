<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBrand;
use Database\Factories\ContentPillarFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentPillar extends Model
{
    /** @use HasFactory<ContentPillarFactory> */
    use BelongsToBrand, HasFactory;

    protected $fillable = [
        'brand_id',
        'name',
        'description',
        'weight',
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
