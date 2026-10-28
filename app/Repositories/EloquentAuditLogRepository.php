<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent implementation of the audit log writes.
 */
class EloquentAuditLogRepository implements AuditLogRepositoryInterface
{
    /** The batch size when masking a long history. */
    private const BATCH = 100;

    /**
     * Adds one line to the history. Lines are never changed or removed.
     */
    public function append(array $attributes): AuditLog
    {
        return AuditLog::query()->create($attributes);
    }

    /**
     * Replaces the personal values in a record's history with a marker, keeping every line and the names of the fields. This is the one change ever made to history, required by the right to erasure.
     */
    public function anonymiseSubject(Model $subject): void
    {
        $this->mask(AuditLog::query()->where('auditable_type', $subject->getMorphClass())->where('auditable_id', $subject->getKey()));
    }

    /**
     * Masks every line of the current church's history, for a church purge.
     */
    public function anonymiseAll(): void
    {
        $this->mask(AuditLog::query());
    }

    /**
     * Masks the values and the reason of every line the query finds, a batch at a time.
     *
     * @param  Builder<AuditLog>  $lines
     */
    private function mask(Builder $lines): void
    {
        $lines->chunkById(self::BATCH, function ($found): void {
            foreach ($found as $line) {
                $line->update([
                    'old_values' => $this->masked($line->old_values),
                    'new_values' => $this->masked($line->new_values),
                    'reason' => $line->reason === null ? null : config('privacy.erased_marker'),
                ]);
            }
        });
    }

    /**
     * Keeps the names of the fields and replaces every value with the erased marker.
     *
     * @param  array<string, mixed>|null  $values
     * @return array<string, string>|null
     */
    private function masked(?array $values): ?array
    {
        return $values === null ? null : array_map(fn (): string => config('privacy.erased_marker'), $values);
    }
}
