<?php

namespace App\Models;

use Database\Factories\CalendarEventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEvent extends Model
{
    /** @use HasFactory<CalendarEventFactory> */
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'date',
        'name',
        'description',
        'recurring',
        'no_posting',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'recurring' => 'boolean',
            'no_posting' => 'boolean',
        ];
    }

    /**
     * Con marca activa se ven los eventos globales (brand_id null = feriados
     * compartidos) más los propios de la marca. Sin marca no se filtra.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('brand', function (Builder $query): void {
            if (app()->bound('brand')) {
                $query->where(fn (Builder $q) => $q
                    ->whereNull('brand_id')
                    ->orWhere('brand_id', app('brand')->id));
            }
        });
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
