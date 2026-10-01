<?php

namespace App\Models;

use Database\Factories\CalendarEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalendarEvent extends Model
{
    /** @use HasFactory<CalendarEventFactory> */
    use HasFactory;

    protected $fillable = [
        'date',
        'name',
        'description',
        'recurring',
        'no_posting',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'recurring' => 'boolean',
            'no_posting' => 'boolean',
        ];
    }
}
