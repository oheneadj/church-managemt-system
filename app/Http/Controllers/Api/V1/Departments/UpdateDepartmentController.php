<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Departments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Departments\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use App\Services\DepartmentService;
use Illuminate\Support\Facades\Gate;

/**
 * Edits or retires a department (docs/08, Update Department). A department of another church is not found (404).
 */
class UpdateDepartmentController extends Controller
{
    public function __construct(
        private readonly DepartmentService $departments,
    ) {}

    public function __invoke(UpdateDepartmentRequest $request, Department $department): DepartmentResource
    {
        Gate::authorize('update', $department);

        return new DepartmentResource($this->departments->update($department, $request->validated()));
    }
}
