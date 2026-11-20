<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Actions\Staff\ChangeStaffStatusAction;
use App\DTOs\Staff\ChangeStaffStatusDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ChangeStaffStatusRequest;
use App\Http\Resources\StaffResource;

/**
 * Deactivates or reactivates a staff member (docs/08, Staff).
 */
class ChangeStaffStatusController extends Controller
{
    /**
     * Takes the action this controller runs.
     */
    public function __construct(private readonly ChangeStaffStatusAction $action) {}

    /**
     * Runs ChangeStaffStatusAction for this request and returns the StaffResource.
     */
    public function __invoke(ChangeStaffStatusRequest $request): StaffResource
    {
        return new StaffResource($this->action->execute(ChangeStaffStatusDTO::fromRequest($request)));
    }
}
