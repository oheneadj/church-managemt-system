<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Export;
use App\Models\User;

/**
 * Builds the file of one kind of export and stores it privately. Each kind of export (the member list, the giving records)
 * has one, so the job, the email and the clean-up do not care what is inside the file.
 */
interface ExportFileBuilder
{
    /**
     * Writes the file for the export, as the staff member who asked (so they get what they may see), and returns where it
     * was stored and how many rows it has.
     *
     * @return array{path: string, rows: int}
     */
    public function build(Export $export, User $requester): array;
}
