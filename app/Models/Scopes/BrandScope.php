<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filtra por la marca activa cuando hay una resuelta en el contenedor
 * (middleware SetActiveBrand o un job que fijó `app('brand')`). En contextos
 * sin marca — CLI, colas antes de fijarla — no filtra.
 */
class BrandScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (app()->bound('brand')) {
            $builder->where($model->qualifyColumn('brand_id'), app('brand')->id);
        }
    }
}
