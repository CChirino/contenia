<?php

namespace App\Models;

use Database\Factories\BrandProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BrandProfile extends Model
{
    /** @use HasFactory<BrandProfileFactory> */
    use HasFactory;

    protected $table = 'brand_profile';

    protected $fillable = [
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
     * Perfil de marca singleton: devuelve la única fila, creándola si no existe.
     */
    public static function current(): self
    {
        return static::firstOrCreate([], [
            'tone_of_voice' => '',
            'audience' => '',
            'base_hashtags' => [],
            'cta' => '',
            'url' => '',
            'palette' => [],
        ]);
    }
}
