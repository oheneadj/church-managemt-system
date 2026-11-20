<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Actions\Staff\ChangeStaffPermissionsAction;
use App\DTOs\Staff\ChangeStaffPermissionsDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ChangeStaffPermissionsRequest;
use App\Http\Resources\StaffResource;

/**
 * Grants and removes single permissions on top of a staff member's role (docs/08, Staff).
 */
class ChangeStaffPermissionsController extends Controller
{
    /**
     * Takes the action this controller runs.
     */
    public function __construct(private readonly ChangeStaffPermissionsAction $action) {}

    /**
     * Runs ChangeStaffPermissionsAction for this request and returns the StaffResource.
     */
    public function __invoke(ChangeStaffPermissionsRequest $request): StaffResource
    {
        return new StaffResource($this->action->execute(ChangeStaffPermissionsDTO::fromRequest($request)));
    }
}
