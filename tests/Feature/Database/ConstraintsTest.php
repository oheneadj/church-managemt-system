<?php

declare(strict_types=1);

use App\Models\AttendanceRecord;
use App\Models\Church;
use App\Models\Member;
use App\Models\Service;
use Illuminate\Database\UniqueConstraintViolationException;

it('stops the same member checking in twice to one service', function (): void {
    $church = Church::factory()->create();
    $service = Service::factory()->for($church)->create();
    $member = Member::factory()->for($church)->create();

    AttendanceRecord::factory()->for($church)->create(['service_id' => $service->id, 'member_id' => $member->id]);

    AttendanceRecord::factory()->for($church)->create(['service_id' => $service->id, 'member_id' => $member->id]);
})->throws(UniqueConstraintViolationException::class);

it('lets two walk-in visitors check in to the same service', function (): void {
    $church = Church::factory()->create();
    $service = Service::factory()->for($church)->create();

    AttendanceRecord::factory()->count(2)->for($church)->create([
        'service_id' => $service->id,
        'member_id' => null,
        'visitor_name' => 'Walk-in',
    ]);

    expect(AttendanceRecord::query()->where('service_id', $service->id)->count())->toBe(2);
});

it('frees a phone number for reuse once its member is soft deleted', function (): void {
    $church = Church::factory()->create();
    $old = Member::factory()->for($church)->create(['phone' => '+233200000001']);
    $old->delete();

    $new = Member::factory()->for($church)->create(['phone' => '+233200000001']);

    expect($new->exists)->toBeTrue();
});

it('rejects the same phone number twice in one church', function (): void {
    $church = Church::factory()->create();
    Member::factory()->for($church)->create(['phone' => '+233200000002']);

    Member::factory()->for($church)->create(['phone' => '+233200000002']);
})->throws(UniqueConstraintViolationException::class);

it('allows the same phone number in two different churches', function (): void {
    Member::factory()->for(Church::factory())->create(['phone' => '+233200000003']);
    $other = Member::factory()->for(Church::factory())->create(['phone' => '+233200000003']);

    expect($other->exists)->toBeTrue();
});
