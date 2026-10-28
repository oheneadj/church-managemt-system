<?php

declare(strict_types=1);

namespace App\Actions\Departments;

use App\DTOs\Departments\AddDepartmentMemberDTO;
use App\DTOs\Departments\DepartmentMembership;
use App\Repositories\MemberDepartmentRepositoryInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use LogicException;

/**
 * Puts a member in a department, or changes their role if they already serve there, so a retried request
 * is harmless. If two requests race, the database keeps one link and the other finds it.
 */
final class AddDepartmentMemberAction
{
    public function __construct(private readonly MemberDepartmentRepositoryInterface $links) {}

    public function execute(AddDepartmentMemberDTO $dto): DepartmentMembership
    {
        $departmentId = $dto->department->getKey();
        $link = $this->links->find($departmentId, $dto->memberId);

        if ($link !== null) {
            return new DepartmentMembership($this->links->changeRole($link, $dto->role), created: false);
        }

        try {
            $link = $this->links->create([
                'church_id' => $dto->department->church_id,
                'member_id' => $dto->memberId,
                'department_id' => $departmentId,
                'role' => $dto->role,
                'joined_at' => today(),
            ]);
        } catch (UniqueConstraintViolationException) {
            $existing = $this->links->find($departmentId, $dto->memberId) ?? throw new LogicException('Link vanished after a unique violation.');

            return new DepartmentMembership($this->links->changeRole($existing, $dto->role), created: false);
        }

        return new DepartmentMembership($link, created: true);
    }
}
