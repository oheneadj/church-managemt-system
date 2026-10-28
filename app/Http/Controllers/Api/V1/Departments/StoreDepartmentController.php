<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Departments;

use App\Actions\Departments\CreateDepartmentAction;
use App\DTOs\Departments\CreateDepartmentDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Departments\StoreDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use Illuminate\Http\JsonResponse;

/**
 * Creates a department (docs/08, Create Department).
 */
class StoreDepartmentController extends Controller
{
    public function __construct(private readonly CreateDepartmentAction $action) {}

    public function __invoke(StoreDepartmentRequest $request): JsonResponse
    {
        $department = $this->action->execute(CreateDepartmentDTO::fromRequest($request));

        return (new DepartmentResource($department))->response()->setStatusCode(201);
    }
}
