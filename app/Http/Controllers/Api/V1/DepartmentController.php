<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Departments\AddDepartmentMemberRequest;
use App\Http\Requests\Departments\ListDepartmentsRequest;
use App\Http\Requests\Departments\StoreDepartmentRequest;
use App\Http\Requests\Departments\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use App\Models\Member;
use App\Services\DepartmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * The department endpoints (docs/08, Departments). A department of another church is not found (404).
 */
class DepartmentController extends Controller
{
    public function __construct(private readonly DepartmentService $departments) {}

    public function index(ListDepartmentsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Department::class);

        return DepartmentResource::collection($this->departments->paginate($this->staff($request), $request->validated()));
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        Gate::authorize('create', Department::class);

        $department = $this->departments->create($this->staff($request), $request->validated());

        return (new DepartmentResource($department))->response()->setStatusCode(201);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): DepartmentResource
    {
        Gate::authorize('update', $department);

        return new DepartmentResource($this->departments->update($department, $request->validated()));
    }

    public function addMember(AddDepartmentMemberRequest $request, Department $department): JsonResponse
    {
        Gate::authorize('update', $department);

        [$link, $created] = $this->departments->addMember($department, $request->validated('member_id'), $request->validated('role'));

        return response()->json(['data' => [
            'department_id' => $link->department_id,
            'member_id' => $link->member_id,
            'role' => $link->role,
            'joined_at' => $link->joined_at?->toDateString(),
        ]], $created ? 201 : 200);
    }

    public function removeMember(Department $department, Member $member): Response
    {
        Gate::authorize('update', $department);

        abort_unless($this->departments->removeMember($department, $member->getKey()), 404);

        return response()->noContent();
    }
}
