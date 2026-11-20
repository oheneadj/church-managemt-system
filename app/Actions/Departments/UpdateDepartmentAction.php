<?php

declare(strict_types=1);

namespace App\Actions\Departments;

use App\DTOs\Departments\UpdateDepartmentDTO;
use App\Models\Department;
use App\Repositories\DepartmentRepositoryInterface;

/**
 * Edits a department, or retires it by setting is_active to false.
 */
final class UpdateDepartmentAction
{
    /**
     * Takes the department repository.
     */
    public function __construct(private readonly DepartmentRepositoryInterface $departments) {}

    /**
     * Edits a department, or retires it by setting is_active to false.
     */
    public function execute(UpdateDepartmentDTO $dto): Department
    {
        return $this->departments->update($dto->department, $dto->attributes);
    }
}
