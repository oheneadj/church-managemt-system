<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Departments;

use App\Actions\Departments\ListDepartmentsAction;
use App\DTOs\Departments\ListDepartmentsDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Departments\ListDepartmentsRequest;
use App\Http\Resources\DepartmentResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * One page of departments (docs/08, List Departments).
 */
class ListDepartmentsController extends Controller
{
    public function __construct(private readonly ListDepartmentsAction $action) {}

    public function __invoke(ListDepartmentsRequest $request): AnonymousResourceCollection
    {
        return DepartmentResource::collection($this->action->execute(ListDepartmentsDTO::fromRequest($request)));
    }
}
