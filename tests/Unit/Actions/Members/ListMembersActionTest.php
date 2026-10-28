<?php

declare(strict_types=1);

use App\Actions\Members\ListMembersAction;
use App\DTOs\Members\ListMembersDTO;
use App\Repositories\MemberRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

it('asks the repository for the page the viewer is allowed to see', function (): void {
    $dto = new ListMembersDTO(viewer: unitUser(), perPage: 25, search: 'Emmanuel');
    $page = new LengthAwarePaginator([], 0, 25);
    $members = Mockery::mock(MemberRepositoryInterface::class);
    $members->shouldReceive('paginate')->once()->with($dto)->andReturn($page);

    expect((new ListMembersAction($members))->execute($dto))->toBe($page);
});
