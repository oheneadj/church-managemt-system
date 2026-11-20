<?php

declare(strict_types=1);

use App\Actions\Departments\RemoveDepartmentMemberAction;
use App\DTOs\Departments\RemoveDepartmentMemberDTO;
use App\Repositories\MemberDepartmentRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

beforeEach(function (): void {
    $this->links = Mockery::mock(MemberDepartmentRepositoryInterface::class);
    $this->dto = new RemoveDepartmentMemberDTO(unitUser(), unitDepartment(), unitMember());
});

it('removes the link', function (): void {
    $link = unitLink();
    $this->links->shouldReceive('find')->once()->with('01deptunit', '01memberunit')->andReturn($link);
    $this->links->shouldReceive('remove')->once()->with($link);

    (new RemoveDepartmentMemberAction($this->links))->execute($this->dto);
});

it('is a not-found error when the member is not in the department', function (): void {
    $this->links->shouldReceive('find')->andReturnNull();
    $this->links->shouldReceive('remove')->never();

    expect(fn () => (new RemoveDepartmentMemberAction($this->links))->execute($this->dto))->toThrow(ModelNotFoundException::class);
});
