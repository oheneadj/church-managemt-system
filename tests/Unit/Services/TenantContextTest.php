<?php

declare(strict_types=1);

use App\Models\Church;
use App\Services\TenantContext;

// Running work as one church, with no request (agreement section 34: background work sets its tenant explicitly).

it('makes the church current while the callback runs and returns what it returns', function (): void {
    $church = Church::factory()->make(['id' => '01churchone']);

    $seen = (new TenantContext)->run($church, fn () => app('current.church')->id);

    expect($seen)->toBe('01churchone');
});

it('leaves no church behind when there was none before', function (): void {
    (new TenantContext)->run(Church::factory()->make(['id' => '01churchone']), fn () => null);

    expect(app()->bound('current.church'))->toBeFalse();
});

it('puts the previous church back afterwards, even when the callback throws', function (): void {
    $before = Church::factory()->make(['id' => '01before']);
    app()->instance('current.church', $before);

    expect(fn () => (new TenantContext)->run(Church::factory()->make(['id' => '01during']), fn () => throw new RuntimeException('boom')))
        ->toThrow(RuntimeException::class);

    expect(app('current.church')->id)->toBe('01before');
});
