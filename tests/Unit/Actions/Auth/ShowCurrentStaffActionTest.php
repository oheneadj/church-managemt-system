<?php

declare(strict_types=1);

use App\Actions\Auth\ShowCurrentStaffAction;
use App\DTOs\Auth\ShowCurrentStaffDTO;

it('returns the staff member together with their church', function (): void {
    $user = unitUser();
    $church = unitChurch();

    $result = (new ShowCurrentStaffAction)->execute(new ShowCurrentStaffDTO($user, $church));

    expect($result->user)->toBe($user)->and($result->church)->toBe($church);
});
