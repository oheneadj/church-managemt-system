<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Departments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Departments\ListDepartmentsRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use App\Services\DepartmentService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * One page of departments (docs/08, List Departments).
 */
class ListDepartmentsController extends Controller
{
    public function __construct(
        private readonly DepartmentService $departments,
    ) {}

    public function __invoke(ListDepartmentsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Department::class);

        return DepartmentResource::collection($this->departments->paginate($this->staff($request), $request->validated()));
    }
}
