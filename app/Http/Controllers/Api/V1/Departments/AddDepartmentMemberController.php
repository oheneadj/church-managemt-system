<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Departments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Departments\AddDepartmentMemberRequest;
use App\Http\Resources\DepartmentMemberResource;
use App\Models\Department;
use App\Services\DepartmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Adds a member to a department, or changes their role if they already serve there (docs/08).
 */
class AddDepartmentMemberController extends Controller
{
    public function __construct(
        private readonly DepartmentService $departments,
    ) {}

    public function __invoke(AddDepartmentMemberRequest $request, Department $department): JsonResponse
    {
        Gate::authorize('update', $department);

        [$link, $created] = $this->departments->addMember($department, $request->validated('member_id'), $request->validated('role'));

        return (new DepartmentMemberResource($link))->response()->setStatusCode($created ? 201 : 200);
    }
}
