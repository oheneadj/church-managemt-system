<?php

declare(strict_types=1);

use App\Models\Church;

it('uses the default youth age limit when the church has not set one', function (): void {
    config(['members.youth_age_limit' => 25]);

    expect(Church::factory()->make(['settings' => null])->youthAgeLimit())->toBe(25);
});

it('uses the church\'s own youth age limit when it sets one', function (): void {
    expect(Church::factory()->make(['settings' => ['youth_age_limit' => 18]])->youthAgeLimit())->toBe(18);
});

it('never allows a limit below one year', function (): void {
    expect(Church::factory()->make(['settings' => ['youth_age_limit' => 0]])->youthAgeLimit())->toBe(1);
});
