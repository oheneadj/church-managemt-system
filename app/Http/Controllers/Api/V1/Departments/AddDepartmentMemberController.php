<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Departments;

use App\Actions\Departments\AddDepartmentMemberAction;
use App\DTOs\Departments\AddDepartmentMemberDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Departments\AddDepartmentMemberRequest;
use App\Http\Resources\DepartmentMemberResource;
use Illuminate\Http\JsonResponse;

/**
 * Adds a member to a department, or changes their role if they already serve there (docs/08).
 */
class AddDepartmentMemberController extends Controller
{
    public function __construct(private readonly AddDepartmentMemberAction $action) {}

    public function __invoke(AddDepartmentMemberRequest $request): JsonResponse
    {
        $membership = $this->action->execute(AddDepartmentMemberDTO::fromRequest($request));

        return (new DepartmentMemberResource($membership->link))->response()->setStatusCode($membership->created ? 201 : 200);
    }
}
