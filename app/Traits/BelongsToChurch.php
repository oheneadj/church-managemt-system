<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Church;
use App\Scopes\ChurchScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Makes a model tenant-owned: every query is scoped to the current church and new rows
 * get that church_id automatically. Any model with a church_id column must use this.
 */
trait BelongsToChurch
{
    /**
     * Adds the church scope to the model and fills church_id on create.
     */
    protected static function bootBelongsToChurch(): void
    {
        static::addGlobalScope(new ChurchScope);

        static::creating(function ($model): void {
            if (empty($model->church_id) && app()->bound('current.church')) {
                $model->church_id = app('current.church')->getKey();
            }
        });
    }

    /**
     * The church this record belongs to.
     *
     * @return BelongsTo<Church, $this>
     */
    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }
}
