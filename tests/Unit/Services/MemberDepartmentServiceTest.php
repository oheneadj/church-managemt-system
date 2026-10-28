<?php

declare(strict_types=1);

use App\Models\MemberDepartment;
use App\Repositories\MemberDepartmentRepositoryInterface;
use App\Services\MemberDepartmentService;

function unitLink(string $departmentId): MemberDepartment
{
    return (new MemberDepartment)->forceFill(['id' => 'link-'.$departmentId, 'department_id' => $departmentId]);
}

it('removes the links no longer wanted and adds the new ones, leaving the rest alone', function (): void {
    $keep = unitLink('01keep');
    $drop = unitLink('01drop');
    $links = Mockery::mock(MemberDepartmentRepositoryInterface::class);
    $links->shouldReceive('forMember')->once()->with('01memberunit')->andReturn(collect([$keep, $drop]));
    $links->shouldReceive('remove')->once()->with($drop);
    $links->shouldReceive('create')->once()->withArgs(fn (array $a) => $a['department_id'] === '01new' && $a['member_id'] === '01memberunit' && $a['church_id'] === '01churchunit')->andReturn(new MemberDepartment);

    (new MemberDepartmentService($links))->sync(unitMember(), ['01keep', '01new']);
});

it('removes every link when the list is empty', function (): void {
    $only = unitLink('01only');
    $links = Mockery::mock(MemberDepartmentRepositoryInterface::class);
    $links->shouldReceive('forMember')->andReturn(collect([$only]));
    $links->shouldReceive('remove')->once()->with($only);
    $links->shouldReceive('create')->never();

    (new MemberDepartmentService($links))->sync(unitMember(), []);
});
