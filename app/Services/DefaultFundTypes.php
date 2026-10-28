<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Church;
use App\Repositories\FundTypeRepositoryInterface;

/**
 * Gives a new church the fund types every church starts with (config/giving.php). It opens no transaction: the
 * caller's does, so a church is never left half set up.
 */
class DefaultFundTypes
{
    /**
     * Takes the fund type repository.
     */
    public function __construct(private readonly FundTypeRepositoryInterface $fundTypes) {}

    /**
     * Adds the default fund types to this church, in the order the config lists them.
     */
    public function createFor(Church $church): void
    {
        $rows = [];

        foreach (config('giving.default_fund_types') as $position => $fund) {
            $rows[] = ['church_id' => $church->id, 'name' => $fund['name'], 'description' => $fund['description'], 'sort_order' => $position];
        }

        $this->fundTypes->createMany($rows);
    }
}
