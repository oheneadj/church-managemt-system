<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Departments;

use App\Actions\Departments\AddDepartmentMemberAction;
use App\DTOs\Departments\AddDepartmentMemberDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Departments\AddDepartmentMemberRequest;
use App\Http\Resources\DepartmentMemberResource;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Adds a member to a department, or changes their role if they already serve there (docs/08).
 */
class AddDepartmentMemberController extends Controller
{
    /**
     * Takes the add department member action.
     */
    public function __construct(private readonly AddDepartmentMemberAction $action) {}

    /**
     * Runs AddDepartmentMemberAction for this request and returns the JsonResponse.
     */
    #[Response(status: HttpResponse::HTTP_CREATED, description: 'The member was added to the department.', type: 'array{data: array{department_id: string, member_id: string, role: string|null, joined_at: string|null}}')]
    #[Response(status: HttpResponse::HTTP_OK, description: 'The member already served there, so only their role changed.', type: 'array{data: array{department_id: string, member_id: string, role: string|null, joined_at: string|null}}')]
    public function __invoke(AddDepartmentMemberRequest $request): JsonResponse
    {
        $membership = $this->action->execute(AddDepartmentMemberDTO::fromRequest($request));

        return (new DepartmentMemberResource($membership->link))->response()->setStatusCode($membership->created ? HttpResponse::HTTP_CREATED : HttpResponse::HTTP_OK);
    }
}
