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

// Queues the purge of churches whose 90-day grace period after cancelling is over (docs/05). 03:30 UTC, after the prune above.
Schedule::command('churches:purge-cancelled')->dailyAt('03:30')->timezone('UTC')->onOneServer();

// The daily database backup (docs/16). 02:00 UTC, before the nightly clean-ups, and never two at once.
Schedule::command('db:backup')->dailyAt('02:00')->timezone('UTC')->onOneServer()->withoutOverlapping();

// Closes services left open after their day ended. Every hour, on the hour, UTC (it only compares dates).
Schedule::command('services:close-stale')->hourly()->timezone('UTC')->onOneServer();
