<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\VisitorRecord;
use Carbon\CarbonInterface;

/**
 * Eloquent implementation of the visitor queries.
 */
class EloquentVisitorRecordRepository implements VisitorRecordRepositoryInterface
{
    /**
     * The visitor of this church with this phone number, if one was recorded before.
     */
    public function findByPhone(string $e164): ?VisitorRecord
    {
        return VisitorRecord::query()->where('phone', $e164)->first();
    }

    /**
     * Adds a visitor record and reloads its database defaults.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): VisitorRecord
    {
        return VisitorRecord::query()->create($attributes)->refresh();
    }

    /**
     * Notes a return visit: one more visit, on the given date.
     */
    public function recordReturn(VisitorRecord $visitor, CarbonInterface $on): VisitorRecord
    {
        $visitor->forceFill(['visit_count' => $visitor->visit_count + 1, 'last_visit_date' => $on])->save();

        return $visitor;
    }
}
