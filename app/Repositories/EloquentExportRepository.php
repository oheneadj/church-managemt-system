<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\ExportStatus;
use App\Models\Export;
use App\Scopes\ChurchScope;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Eloquent implementation of the export queries.
 */
class EloquentExportRepository implements ExportRepositoryInterface
{
    /**
     * Records a new export.
     */
    public function create(array $attributes): Export
    {
        return Export::query()->create($attributes);
    }

    /**
     * The export with this id, in any church.
     */
    public function find(string $id): ?Export
    {
        return Export::query()->withoutGlobalScope(ChurchScope::class)->find($id);
    }

    /**
     * Records that a worker started building the file.
     */
    public function markProcessing(Export $export): Export
    {
        $export->update(['status' => ExportStatus::Processing]);

        return $export;
    }

    /**
     * Records that the file is stored.
     */
    public function markCompleted(Export $export, string $path, int $rows): Export
    {
        $export->update(['status' => ExportStatus::Completed, 'file_path' => $path, 'row_count' => $rows, 'completed_at' => now()]);

        return $export;
    }

    /**
     * Records that the export failed for good.
     */
    public function markFailed(Export $export): Export
    {
        $export->update(['status' => ExportStatus::Failed]);

        return $export;
    }

    /**
     * The finished exports whose file is still stored and was finished before this moment, in every church.
     */
    public function withFilesFinishedBefore(CarbonInterface $before): Collection
    {
        return Export::query()
            ->withoutGlobalScope(ChurchScope::class)
            ->where('status', ExportStatus::Completed->value)
            ->whereNotNull('file_path')
            ->where('completed_at', '<', $before)
            ->get();
    }

    /**
     * Records that the file was deleted and forgets where it was.
     */
    public function markFileDeleted(Export $export): Export
    {
        $export->update(['file_path' => null, 'file_deleted_at' => now()]);

        return $export;
    }

    /**
     * Forgets the filters and the file path of every export of the current church.
     */
    public function clearDetails(): void
    {
        Export::query()->withTrashed()->update(['filters' => null, 'file_path' => null]);
    }
}
