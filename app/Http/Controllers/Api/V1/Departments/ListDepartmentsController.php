<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Departments;

use App\Actions\Departments\ListDepartmentsAction;
use App\DTOs\Departments\ListDepartmentsDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Departments\ListDepartmentsRequest;
use App\Http\Resources\DepartmentCollection;

/**
 * One page of departments (docs/08, List Departments).
 */
class ListDepartmentsController extends Controller
{
    /**
     * Takes the list departments action.
     */
    public function __construct(private readonly ListDepartmentsAction $action) {}

    /**
     * Runs ListDepartmentsAction for this request and returns the DepartmentCollection.
     */
    public function __invoke(ListDepartmentsRequest $request): DepartmentCollection
    {
        return new DepartmentCollection($this->action->execute(ListDepartmentsDTO::fromRequest($request)));
    }
}
