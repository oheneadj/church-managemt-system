<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\MessageLog;

/**
 * Every query on the message log lives behind this contract.
 */
interface MessageLogRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): MessageLog;
}
