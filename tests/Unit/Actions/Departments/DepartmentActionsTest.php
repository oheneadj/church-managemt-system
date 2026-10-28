<?php

declare(strict_types=1);

use App\Actions\Departments\CreateDepartmentAction;
use App\Actions\Departments\ListDepartmentsAction;
use App\Actions\Departments\UpdateDepartmentAction;
use App\DTOs\Departments\CreateDepartmentDTO;
use App\DTOs\Departments\ListDepartmentsDTO;
use App\DTOs\Departments\UpdateDepartmentDTO;
use App\Repositories\DepartmentRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

it('lists a page of departments through the repository', function (): void {
    $dto = new ListDepartmentsDTO(unitUser(), isActive: true);
    $page = new LengthAwarePaginator([], 0, 25);
    $departments = Mockery::mock(DepartmentRepositoryInterface::class);
    $departments->shouldReceive('paginate')->once()->with($dto)->andReturn($page);

    expect((new ListDepartmentsAction($departments))->execute($dto))->toBe($page);
});

it('creates a department in the staff member\'s church', function (): void {
    $department = unitDepartment();
    $departments = Mockery::mock(DepartmentRepositoryInterface::class);
    $departments->shouldReceive('create')->once()->with([
        'church_id' => '01churchunit',
        'name' => 'Choir',
        'description' => 'Sunday choir',
        'head_user_id' => null,
        'is_active' => true,
    ])->andReturn($department);

    $result = (new CreateDepartmentAction($departments))->execute(new CreateDepartmentDTO(unitUser(), 'Choir', 'Sunday choir'));

    expect($result)->toBe($department);
});

it('updates only the fields given', function (): void {
    $department = unitDepartment();
    $departments = Mockery::mock(DepartmentRepositoryInterface::class);
    $departments->shouldReceive('update')->once()->with($department, ['is_active' => false])->andReturn($department);

    $result = (new UpdateDepartmentAction($departments))->execute(new UpdateDepartmentDTO(unitUser(), $department, ['is_active' => false]));

    expect($result)->toBe($department);
});
