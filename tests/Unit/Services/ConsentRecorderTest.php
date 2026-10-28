<?php

declare(strict_types=1);

use App\Enums\ConsentPurpose;
use App\Models\Consent;
use App\Repositories\ConsentRepositoryInterface;
use App\Services\ConsentRecorder;

beforeEach(function (): void {
    $this->consents = Mockery::mock(ConsentRepositoryInterface::class);
    $this->recorder = new ConsentRecorder($this->consents);
    $this->actor = unitUser();
});

it('records an agreement for a purpose that is on and has no open agreement', function (): void {
    $member = unitMember(['sms_opted_in' => true, 'whatsapp_opted_in' => false]);
    $this->consents->shouldReceive('openFor')->twice()->andReturnNull();
    $this->consents->shouldReceive('create')->once()->withArgs(fn (array $row): bool => $row['purpose'] === ConsentPurpose::Sms
        && $row['member_id'] === $member->id && $row['recorded_by'] === $this->actor->id && $row['policy_version'] === config('privacy.policy_version'));

    $this->recorder->sync($member, $this->actor);
});

it('withdraws the open agreement of a purpose that is switched off', function (): void {
    $member = unitMember(['sms_opted_in' => false, 'whatsapp_opted_in' => false]);
    $open = new Consent;
    $this->consents->shouldReceive('openFor')->with($member, ConsentPurpose::Sms)->andReturn($open);
    $this->consents->shouldReceive('openFor')->with($member, ConsentPurpose::Whatsapp)->andReturnNull();
    $this->consents->shouldReceive('withdraw')->once()->with($open);
    $this->consents->shouldNotReceive('create');

    $this->recorder->sync($member, $this->actor);
});

it('does nothing when the choice already matches the history', function (): void {
    $member = unitMember(['sms_opted_in' => true, 'whatsapp_opted_in' => false]);
    $this->consents->shouldReceive('openFor')->with($member, ConsentPurpose::Sms)->andReturn(new Consent);
    $this->consents->shouldReceive('openFor')->with($member, ConsentPurpose::Whatsapp)->andReturnNull();
    $this->consents->shouldNotReceive('create');
    $this->consents->shouldNotReceive('withdraw');

    $this->recorder->sync($member, $this->actor);
});

it('can record with no actor', function (): void {
    $member = unitMember(['sms_opted_in' => true, 'whatsapp_opted_in' => false]);
    $this->consents->shouldReceive('openFor')->twice()->andReturnNull();
    $this->consents->shouldReceive('create')->once()->withArgs(fn (array $row): bool => $row['recorded_by'] === null);

    $this->recorder->sync($member, null);
});
