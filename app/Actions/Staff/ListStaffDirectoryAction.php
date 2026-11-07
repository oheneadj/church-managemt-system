<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\DTOs\Staff\ListStaffDTO;
use App\Models\User;
use App\Repositories\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Lists one page of the church's active staff, names only, for a screen that has to choose one of them.
 */
final class ListStaffDirectoryAction
{
    /**
     * Takes the staff repository.
     */
    public function __construct(private readonly UserRepositoryInterface $users) {}

    /**
     * Lists one page of active staff.
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function execute(ListStaffDTO $dto): LengthAwarePaginator
    {
        return $this->users->paginateActive($dto->perPage);
    }
}
