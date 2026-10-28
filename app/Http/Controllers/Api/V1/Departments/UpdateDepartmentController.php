<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Departments;

use App\Actions\Departments\UpdateDepartmentAction;
use App\DTOs\Departments\UpdateDepartmentDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Departments\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;

/**
 * Edits or retires a department (docs/08, Update Department). A department of another church is not found (404).
 */
class UpdateDepartmentController extends Controller
{
    /**
     * Takes the update department action.
     */
    public function __construct(private readonly UpdateDepartmentAction $action) {}

    /**
     * Runs UpdateDepartmentAction for this request and returns the DepartmentResource.
     */
    public function __invoke(UpdateDepartmentRequest $request): DepartmentResource
    {
        return new DepartmentResource($this->action->execute(UpdateDepartmentDTO::fromRequest($request)));
    }
}
