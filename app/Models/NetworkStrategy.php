<?php

namespace App\Models;

use App\Enums\Network;
use Database\Factories\NetworkStrategyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NetworkStrategy extends Model
{
    /** @use HasFactory<NetworkStrategyFactory> */
    use HasFactory;

    protected $fillable = [
        'network',
        'posts_per_week',
        'preferred_formats',
        'focus',
    ];

    protected function casts(): array
    {
        return [
            'network' => Network::class,
            'preferred_formats' => 'array',
        ];
    }
}
