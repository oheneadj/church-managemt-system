<?php

declare(strict_types=1);

namespace App\Actions\Members;

use App\DTOs\Members\ExportMemberDataDTO;
use App\DTOs\Members\MemberDataExport;
use App\Events\MemberDataExported;
use App\Services\MemberDataExporter;
use Illuminate\Support\Facades\DB;

/**
 * Exports everything held about a member (a subject access request) and records in the change log who did it.
 */
final class ExportMemberDataAction
{
    /**
     * Takes the exporter.
     */
    public function __construct(private readonly MemberDataExporter $exporter) {}

    /**
     * Gathers the data and records the export, together or not at all.
     */
    public function execute(ExportMemberDataDTO $dto): MemberDataExport
    {
        return DB::transaction(function () use ($dto): MemberDataExport {
            $export = $this->exporter->gather($dto->member);
            MemberDataExported::dispatch($dto->member, $dto->actor);

            return $export;
        });
    }
}
