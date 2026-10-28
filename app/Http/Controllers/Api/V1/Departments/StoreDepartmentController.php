<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Departments;

use App\Actions\Departments\CreateDepartmentAction;
use App\DTOs\Departments\CreateDepartmentDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Departments\StoreDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Creates a department (docs/08, Create Department).
 */
class StoreDepartmentController extends Controller
{
    /**
     * Takes the create department action.
     */
    public function __construct(private readonly CreateDepartmentAction $action) {}

    /**
     * Runs CreateDepartmentAction for this request and returns the JsonResponse.
     */
    public function __invoke(StoreDepartmentRequest $request): JsonResponse
    {
        $department = $this->action->execute(CreateDepartmentDTO::fromRequest($request));

        return (new DepartmentResource($department))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
