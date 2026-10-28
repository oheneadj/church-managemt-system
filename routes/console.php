<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Deletes records past their retention period (config/retention.php). 03:00 UTC, when the platform is quietest (docs/05).
Schedule::command('model:prune')->dailyAt('03:00')->timezone('UTC')->onOneServer();
