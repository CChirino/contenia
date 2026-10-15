<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBrand;
use Database\Factories\LlmLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LlmLog extends Model
{
    /** @use HasFactory<LlmLogFactory> */
    use BelongsToBrand, HasFactory;

    protected $fillable = [
        'brand_id',
        'purpose',
        'prompt',
        'response',
        'input_tokens',
        'output_tokens',
        'cost_usd',
        'model',
        'duration_ms',
    ];
}
