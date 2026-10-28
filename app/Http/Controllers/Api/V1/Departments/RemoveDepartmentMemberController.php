<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Departments;

use App\Actions\Departments\RemoveDepartmentMemberAction;
use App\DTOs\Departments\RemoveDepartmentMemberDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Departments\RemoveDepartmentMemberRequest;
use Illuminate\Http\Response;

/**
 * Takes a member out of a department (docs/08). Not being in it is a 404.
 */
class RemoveDepartmentMemberController extends Controller
{
    public function __construct(private readonly RemoveDepartmentMemberAction $action) {}

    public function __invoke(RemoveDepartmentMemberRequest $request): Response
    {
        $this->action->execute(RemoveDepartmentMemberDTO::fromRequest($request));

        return response()->noContent();
    }
}
