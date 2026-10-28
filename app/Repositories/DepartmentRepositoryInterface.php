<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\Departments\ListDepartmentsDTO;
use App\Models\Department;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Every query on departments lives behind this contract.
 */
interface DepartmentRepositoryInterface
{
    /**
     * One page of departments. The member count only counts members the viewer may see.
     *
     * @return LengthAwarePaginator<int, Department>
     */
    public function paginate(ListDepartmentsDTO $filters): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Department;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Department $department, array $attributes): Department;
}
