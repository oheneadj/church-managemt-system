<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Departments;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Member;
use App\Services\DepartmentService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Takes a member out of a department (docs/08). Not being in it is a 404.
 */
class RemoveDepartmentMemberController extends Controller
{
    public function __construct(
        private readonly DepartmentService $departments,
    ) {}

    public function __invoke(Department $department, Member $member): Response
    {
        Gate::authorize('update', $department);

        $this->departments->removeMember($department, $member->getKey());

        return response()->noContent();
    }
}
