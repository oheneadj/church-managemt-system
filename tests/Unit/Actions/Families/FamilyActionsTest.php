<?php

declare(strict_types=1);

use App\Actions\Families\ListFamiliesAction;
use App\Actions\Families\ShowFamilyAction;
use App\Actions\Families\UpdateFamilyAction;
use App\DTOs\Families\ListFamiliesDTO;
use App\DTOs\Families\ShowFamilyDTO;
use App\DTOs\Families\UpdateFamilyDTO;
use App\Repositories\FamilyRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

it('lists a page of families through the repository', function (): void {
    $dto = new ListFamiliesDTO(unitUser(), 'Mensah');
    $page = new LengthAwarePaginator([], 0, 25);
    $families = Mockery::mock(FamilyRepositoryInterface::class);
    $families->shouldReceive('paginate')->once()->with($dto)->andReturn($page);

    expect((new ListFamiliesAction($families))->execute($dto))->toBe($page);
});

it('shows a family with the members the viewer may see', function (): void {
    $viewer = unitUser();
    $family = unitFamily();
    $families = Mockery::mock(FamilyRepositoryInterface::class);
    $families->shouldReceive('loadWithVisibleMembers')->once()->with($family, $viewer)->andReturn($family);

    expect((new ShowFamilyAction($families))->execute(new ShowFamilyDTO($viewer, $family)))->toBe($family);
});

it('updates the family, then loads it for the viewer', function (): void {
    $actor = unitUser();
    $family = unitFamily();
    $families = Mockery::mock(FamilyRepositoryInterface::class);
    $families->shouldReceive('update')->once()->with($family, ['name' => 'New'])->andReturn($family);
    $families->shouldReceive('loadWithVisibleMembers')->once()->with($family, $actor)->andReturn($family);

    expect((new UpdateFamilyAction($families))->execute(new UpdateFamilyDTO($actor, $family, ['name' => 'New'])))->toBe($family);
});
