<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBrand;
use Database\Factories\BrandProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BrandProfile extends Model
{
    /** @use HasFactory<BrandProfileFactory> */
    use BelongsToBrand, HasFactory;

    protected $table = 'brand_profile';

    protected $fillable = [
        'brand_id',
        'description',
        'tone_of_voice',
        'audience',
        'base_hashtags',
        'cta',
        'url',
        'palette',
    ];

    protected function casts(): array
    {
        return [
            'base_hashtags' => 'array',
            'palette' => 'array',
        ];
    }

    /**
     * Perfil de la marca activa, creado si no existe.
     */
    public static function current(): self
    {
        return Brand::current()->profile()->firstOrCreate([], [
            'tone_of_voice' => '',
            'audience' => '',
            'base_hashtags' => [],
            'cta' => '',
            'url' => '',
            'palette' => [],
        ]);
    }
}
