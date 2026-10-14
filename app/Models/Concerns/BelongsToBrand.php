<?php

namespace App\Models\Concerns;

use App\Models\Brand;
use App\Models\Scopes\BrandScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToBrand
{
    public static function bootBelongsToBrand(): void
    {
        static::addGlobalScope(new BrandScope);

        static::creating(function (Model $model): void {
            if ($model->brand_id === null && app()->bound('brand')) {
                $model->brand_id = app('brand')->id;
            }
        });
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
