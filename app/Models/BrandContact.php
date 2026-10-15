<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBrand;
use Database\Factories\BrandContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class BrandContact extends Model
{
    /** @use HasFactory<BrandContactFactory> */
    use BelongsToBrand, HasFactory;

    protected $fillable = [
        'brand_id',
        'name',
        'email',
        'whatsapp',
    ];
}
