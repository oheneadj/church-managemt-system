<?php

declare(strict_types=1);

use App\Actions\Services\CreateServiceAction;
use App\DTOs\Services\CreateServiceDTO;
use App\Enums\ServiceType;
use App\Models\Service;
use App\Services\ServiceCreator;

it('hands the typed details and the actor\'s church to the creator', function (): void {
    $actor = unitUser();
    $creator = Mockery::mock(ServiceCreator::class);
    $creator->shouldReceive('create')->once()->with([
        'church_id' => $actor->church_id, 'name' => 'Sunday Service', 'type' => ServiceType::Sunday, 'service_date' => '2026-10-25',
        'starts_at' => '09:00', 'ends_at' => null, 'notes' => null, 'created_by' => $actor->id,
    ])->andReturn(new Service);

    (new CreateServiceAction($creator))->execute(new CreateServiceDTO($actor, 'Sunday Service', ServiceType::Sunday, '2026-10-25', '09:00'));
});
