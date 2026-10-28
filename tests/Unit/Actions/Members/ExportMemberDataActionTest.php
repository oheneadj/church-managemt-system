<?php

declare(strict_types=1);

use App\Actions\Members\ExportMemberDataAction;
use App\DTOs\Members\ExportMemberDataDTO;
use App\DTOs\Members\MemberDataExport;
use App\Events\MemberDataExported;
use App\Services\MemberDataExporter;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    Event::fake([MemberDataExported::class]);
    withoutDatabaseTransactions();
    $this->exporter = Mockery::mock(MemberDataExporter::class);
    $this->action = new ExportMemberDataAction($this->exporter);
    $this->dto = new ExportMemberDataDTO(unitUser(), unitMember(persisted: true));
});

it('gathers the data and records the export', function (): void {
    $export = new MemberDataExport($this->dto->member, now()->toImmutable());
    $this->exporter->shouldReceive('gather')->once()->with($this->dto->member)->andReturn($export);

    expect($this->action->execute($this->dto))->toBe($export);
    Event::assertDispatched(MemberDataExported::class);
});

it('records nothing when the data cannot be gathered', function (): void {
    $this->exporter->shouldReceive('gather')->once()->andThrow(new RuntimeException('db down'));

    expect(fn () => $this->action->execute($this->dto))->toThrow(RuntimeException::class);
    Event::assertNotDispatched(MemberDataExported::class);
});
