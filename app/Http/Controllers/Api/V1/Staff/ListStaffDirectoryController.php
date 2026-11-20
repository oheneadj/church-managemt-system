<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Actions\Staff\ListStaffDirectoryAction;
use App\DTOs\Staff\ListStaffDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ListStaffDirectoryRequest;
use App\Http\Resources\StaffSummaryResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * One page of the church's active staff, names only (docs/08, Staff Directory).
 */
class ListStaffDirectoryController extends Controller
{
    /**
     * Takes the action this controller runs.
     */
    public function __construct(private readonly ListStaffDirectoryAction $action) {}

    /**
     * Runs ListStaffDirectoryAction for this request and returns each staff member as an id and a name.
     */
    public function __invoke(ListStaffDirectoryRequest $request): AnonymousResourceCollection
    {
        return StaffSummaryResource::collection($this->action->execute(ListStaffDTO::fromRequest($request)));
    }
}
