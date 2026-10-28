<?php

declare(strict_types=1);

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits every query on a tenant-owned model to the church resolved for this request.
 * I only filter when a church is bound, so console commands and seeders can run without one.
 *
 * @implements Scope<Model>
 */
final class ChurchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (app()->bound('current.church')) {
            $builder->where($model->qualifyColumn('church_id'), app('current.church')->getKey());
        }
    }
}
