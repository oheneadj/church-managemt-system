<?php

declare(strict_types=1);

use App\Actions\Members\EraseMemberAction;
use App\DTOs\Members\EraseMemberDTO;
use App\Events\MemberErased;
use App\Services\MemberErasureService;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    Event::fake([MemberErased::class]);
    withoutDatabaseTransactions();
    $this->erasure = Mockery::mock(MemberErasureService::class);
    $this->action = new EraseMemberAction($this->erasure);
    $this->dto = new EraseMemberDTO(unitUser(), unitMember(persisted: true), 'Member request');
});

it('erases the member first and then fires MemberErased with the reason', function (): void {
    $this->erasure->shouldReceive('erase')->once()->with($this->dto->member)->andReturnUsing(function (): void {
        Event::assertNotDispatched(MemberErased::class);
    });

    $this->action->execute($this->dto);

    Event::assertDispatched(MemberErased::class, fn (MemberErased $event): bool => $event->reason === 'Member request' && $event->member === $this->dto->member);
});

it('fires no event when the erasure fails', function (): void {
    $this->erasure->shouldReceive('erase')->once()->andThrow(new RuntimeException('storage down'));

    expect(fn () => $this->action->execute($this->dto))->toThrow(RuntimeException::class);
    Event::assertNotDispatched(MemberErased::class);
});
