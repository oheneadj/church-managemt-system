<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\Departments\ListDepartmentsDTO;
use App\Models\Department;
use App\Models\Member;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Eloquent implementation of the department queries.
 */
class EloquentDepartmentRepository implements DepartmentRepositoryInterface
{
    /**
     * One page of departments, counting only the members the viewer may see.
     */
    public function paginate(ListDepartmentsDTO $filters): LengthAwarePaginator
    {
        return Department::query()
            ->withCount(['members' => function (Builder $members) use ($filters): void {
                /** @var Builder<Member> $members */
                $members->visibleTo($filters->viewer);
            }])
            ->when($filters->isActive !== null, fn (Builder $q) => $q->where('is_active', $filters->isActive))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($filters->perPage);
    }

    /**
     * Creates a department and reloads its database defaults.
     */
    public function create(array $attributes): Department
    {
        return Department::query()->create($attributes)->refresh();
    }

    /**
     * Changes a department.
     */
    public function update(Department $department, array $attributes): Department
    {
        $department->update($attributes);

        return $department;
    }
}
