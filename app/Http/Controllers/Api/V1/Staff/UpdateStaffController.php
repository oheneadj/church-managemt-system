<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Actions\Staff\UpdateStaffAction;
use App\DTOs\Staff\UpdateStaffDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\UpdateStaffRequest;
use App\Http\Resources\StaffResource;

/**
 * Changes a staff member's name, phone or role (docs/08, Staff).
 */
class UpdateStaffController extends Controller
{
    /**
     * Takes the action this controller runs.
     */
    public function __construct(private readonly UpdateStaffAction $action) {}

    /**
     * Runs UpdateStaffAction for this request and returns the StaffResource.
     */
    public function __invoke(UpdateStaffRequest $request): StaffResource
    {
        return new StaffResource($this->action->execute(UpdateStaffDTO::fromRequest($request)));
    }
}
