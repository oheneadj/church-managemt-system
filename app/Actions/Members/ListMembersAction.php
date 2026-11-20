<?php

declare(strict_types=1);

namespace App\Actions\Members;

use App\DTOs\Members\ListMembersDTO;
use App\Models\Member;
use App\Repositories\MemberRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Lists one page of the member directory for the viewer.
 */
final class ListMembersAction
{
    /**
     * Takes the member repository.
     */
    public function __construct(private readonly MemberRepositoryInterface $members) {}

    /**
     * Lists one page of the member directory for the viewer.
     *
     * @return LengthAwarePaginator<int, Member>
     */
    public function execute(ListMembersDTO $dto): LengthAwarePaginator
    {
        return $this->members->paginate($dto);
    }
}
