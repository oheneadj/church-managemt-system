<?php

declare(strict_types=1);

use App\Actions\Services\CreateServiceAction;
use App\DTOs\Services\CreateServiceDTO;
use App\Enums\ServiceType;
use App\Models\Service;
use App\Repositories\ServiceRepositoryInterface;
use App\Services\CheckinCodeGenerator;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(function (): void {
    withoutDatabaseTransactions();
    $this->services = Mockery::mock(ServiceRepositoryInterface::class);
    $this->codes = Mockery::mock(CheckinCodeGenerator::class);
    $this->action = new CreateServiceAction($this->services, $this->codes);
    $this->dto = new CreateServiceDTO(unitUser(), 'Sunday Service', ServiceType::Sunday, '2026-10-25');
});

it('tries a new code when the database says two services got the same one', function (): void {
    $this->codes->shouldReceive('next')->twice()->andReturn('AAAAAA', 'BBBBBB');
    $this->services->shouldReceive('create')->twice()->andReturnUsing(function (array $row): Service {
        if ($row['checkin_code'] === 'AAAAAA') {
            throw new UniqueConstraintViolationException('pgsql', 'insert', [], new PDOException('duplicate'));
        }

        return new Service($row);
    });
    $this->services->shouldReceive('withAttendanceCount')->once()->andReturnArg(0);

    expect($this->action->execute($this->dto)->checkin_code)->toBe('BBBBBB');
});

it('gives up after the allowed number of clashes', function (): void {
    config(['attendance.checkin_code_attempts' => 3]);
    $this->codes->shouldReceive('next')->times(3)->andReturn('AAAAAA');
    $this->services->shouldReceive('create')->times(3)->andThrow(new UniqueConstraintViolationException('pgsql', 'insert', [], new PDOException('duplicate')));

    expect(fn () => $this->action->execute($this->dto))->toThrow(UniqueConstraintViolationException::class);
});
