<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

it('runs against PostgreSQL so partial indexes behave as in production', function (): void {
    expect(DB::connection()->getDriverName())->toBe('pgsql');
});
