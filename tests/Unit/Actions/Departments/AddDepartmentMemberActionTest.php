<?php

declare(strict_types=1);

use App\Actions\Departments\AddDepartmentMemberAction;
use App\DTOs\Departments\AddDepartmentMemberDTO;
use App\Models\Department;
use App\Models\MemberDepartment;
use App\Repositories\MemberDepartmentRepositoryInterface;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(function (): void {
    $this->department = (new Department)->forceFill(['id' => '01deptunit', 'church_id' => '01churchunit']);
    $this->links = Mockery::mock(MemberDepartmentRepositoryInterface::class);
    $this->action = new AddDepartmentMemberAction($this->links);
    $this->dto = new AddDepartmentMemberDTO(unitUser(), $this->department, '01memberunit', 'Leader');
});

it('adds a member who is not in the department yet', function (): void {
    $link = new MemberDepartment;
    $this->links->shouldReceive('find')->once()->with('01deptunit', '01memberunit')->andReturnNull();
    $this->links->shouldReceive('create')->once()->withArgs(fn (array $a) => $a['church_id'] === '01churchunit' && $a['member_id'] === '01memberunit' && $a['department_id'] === '01deptunit' && $a['role'] === 'Leader')->andReturn($link);

    $result = $this->action->execute($this->dto);

    expect($result->link)->toBe($link)->and($result->created)->toBeTrue();
});

it('only changes the role of a member who already serves there', function (): void {
    $link = new MemberDepartment;
    $this->links->shouldReceive('find')->once()->andReturn($link);
    $this->links->shouldReceive('create')->never();
    $this->links->shouldReceive('changeRole')->once()->with($link, 'Leader')->andReturn($link);

    $result = $this->action->execute($this->dto);

    expect($result->created)->toBeFalse();
});

it('treats a lost race as a role change, not an error', function (): void {
    $existing = new MemberDepartment;
    $this->links->shouldReceive('find')->twice()->andReturn(null, $existing);
    $this->links->shouldReceive('create')->once()->andThrow(new UniqueConstraintViolationException('pgsql', 'insert', [], new Exception('duplicate key')));
    $this->links->shouldReceive('changeRole')->once()->with($existing, 'Leader')->andReturn($existing);

    $result = $this->action->execute($this->dto);

    expect($result->link)->toBe($existing)->and($result->created)->toBeFalse();
});
