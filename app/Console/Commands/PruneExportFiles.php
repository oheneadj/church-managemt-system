<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Repositories\ExportRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes the stored CSV files of exports finished more than `config/exports.php` days ago (D-064). The files list people's
 * contact details, so they do not stay for ever; the export's record, which shows who exported what and when, stays.
 * Safe to run again: a file already gone is simply forgotten.
 */
class PruneExportFiles extends Command
{
    protected $signature = 'exports:prune-files';

    protected $description = 'Delete the stored files of old CSV exports, keeping their records';

    /**
     * Deletes the old files, in every church.
     */
    public function handle(ExportRepositoryInterface $exports): int
    {
        $old = $exports->withFilesFinishedBefore(now()->subDays((int) config('exports.file_days')));

        foreach ($old as $export) {
            Storage::delete((string) $export->file_path);
            $exports->markFileDeleted($export);
        }

        $this->info("Deleted {$old->count()} export file(s).");

        return self::SUCCESS;
    }
}
