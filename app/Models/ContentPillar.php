<?php

namespace App\Models;

use Database\Factories\ContentPillarFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentPillar extends Model
{
    /** @use HasFactory<ContentPillarFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'weight',
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
