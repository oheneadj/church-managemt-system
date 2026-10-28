<?php

declare(strict_types=1);

namespace App\Actions\Departments;

use App\DTOs\Departments\CreateDepartmentDTO;
use App\Models\Department;
use App\Repositories\DepartmentRepositoryInterface;

/**
 * Creates a department in the staff member's church.
 */
final class CreateDepartmentAction
{
    /**
     * Takes the department repository.
     */
    public function __construct(private readonly DepartmentRepositoryInterface $departments) {}

    /**
     * Creates a department in the staff member's church.
     */
    public function execute(CreateDepartmentDTO $dto): Department
    {
        return $this->departments->create([
            'church_id' => $dto->actor->church_id,
            'name' => $dto->name,
            'description' => $dto->description,
            'head_user_id' => $dto->headUserId,
            'is_active' => $dto->isActive,
        ]);
    }
}
