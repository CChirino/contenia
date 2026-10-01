<?php

namespace App\Models;

use Database\Factories\LlmLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LlmLog extends Model
{
    /** @use HasFactory<LlmLogFactory> */
    use HasFactory;

    protected $fillable = [
        'purpose',
        'prompt',
        'response',
        'input_tokens',
        'output_tokens',
        'model',
        'duration_ms',
    ];
}
