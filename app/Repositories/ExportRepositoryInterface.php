<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Export;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Every query on exports lives behind this contract.
 */
interface ExportRepositoryInterface
{
    /**
     * Records a new export.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Export;

    /**
     * The export with this id, if there is one. Read from a job, which runs with no church in context.
     */
    public function find(string $id): ?Export;

    /**
     * Records that a worker started building the file.
     */
    public function markProcessing(Export $export): Export;

    /**
     * Records that the file is stored, with where it is and how many rows it has.
     */
    public function markCompleted(Export $export, string $path, int $rows): Export;

    /**
     * Records that the export failed for good.
     */
    public function markFailed(Export $export): Export;

    /**
     * The finished exports, in every church, whose file is still stored and was finished before this moment.
     *
     * @return Collection<int, Export>
     */
    public function withFilesFinishedBefore(CarbonInterface $before): Collection;

    /**
     * Records that the file was deleted and forgets where it was. The record itself stays.
     */
    public function markFileDeleted(Export $export): Export;

    /**
     * Forgets the filters and the file path of every export of the current church, for a church purge (a search can hold a name).
     */
    public function clearDetails(): void;
}
