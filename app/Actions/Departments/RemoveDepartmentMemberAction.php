<?php

declare(strict_types=1);

namespace App\Actions\Departments;

use App\DTOs\Departments\RemoveDepartmentMemberDTO;
use App\Models\MemberDepartment;
use App\Repositories\MemberDepartmentRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Takes a member out of a department by soft deleting the link, so the history is kept.
 *
 * @throws ModelNotFoundException when the member is not in the department
 */
final class RemoveDepartmentMemberAction
{
    public function __construct(private readonly MemberDepartmentRepositoryInterface $links) {}

    public function execute(RemoveDepartmentMemberDTO $dto): void
    {
        $link = $this->links->find($dto->department->getKey(), $dto->member->getKey())
            ?? throw (new ModelNotFoundException)->setModel(MemberDepartment::class);

        $this->links->remove($link);
    }
}
