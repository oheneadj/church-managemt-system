<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Members;

use App\Actions\Members\ExportMemberDataAction;
use App\DTOs\Members\ExportMemberDataDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Members\ExportMemberDataRequest;
use App\Http\Resources\MemberDataExportResource;

/**
 * Exports everything held about one member as JSON (docs/08, Export Member Data).
 */
class ExportMemberDataController extends Controller
{
    /**
     * Takes the export member data action.
     */
    public function __construct(private readonly ExportMemberDataAction $action) {}

    /**
     * Runs ExportMemberDataAction for this request and returns the MemberDataExportResource.
     */
    public function __invoke(ExportMemberDataRequest $request): MemberDataExportResource
    {
        return new MemberDataExportResource($this->action->execute(ExportMemberDataDTO::fromRequest($request)));
    }
}
