<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\VisitorRecord;
use Carbon\CarbonInterface;

/**
 * Every query on visitor records lives behind this contract.
 */
interface VisitorRecordRepositoryInterface
{
    /**
     * The first-visit dates (YYYY-MM-DD), one for each visitor who first came from the first date to the last (both included).
     *
     * @return list<string>
     */
    public function firstVisitDatesBetween(CarbonInterface $from, CarbonInterface $to): array;

    /**
     * The visitor of this church with this phone number, if one was recorded before.
     */
    public function findByPhone(string $e164): ?VisitorRecord;

    /**
     * Adds a visitor record and reloads its database defaults.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): VisitorRecord;

    /**
     * Notes a return visit: one more visit, on the given date.
     */
    public function recordReturn(VisitorRecord $visitor, CarbonInterface $on): VisitorRecord;
}
