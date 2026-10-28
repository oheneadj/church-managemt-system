<?php

declare(strict_types=1);

namespace App\Actions\Departments;

use App\DTOs\Departments\ListDepartmentsDTO;
use App\Models\Department;
use App\Repositories\DepartmentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Lists one page of departments. The member counts only include members the viewer may see.
 */
final class ListDepartmentsAction
{
    public function __construct(private readonly DepartmentRepositoryInterface $departments) {}

    /**
     * @return LengthAwarePaginator<int, Department>
     */
    public function execute(ListDepartmentsDTO $dto): LengthAwarePaginator
    {
        return $this->departments->paginate($dto);
    }
}
