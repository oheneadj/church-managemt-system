<?php

declare(strict_types=1);

use App\Models\Church;
use App\Models\MessageLog;
use App\Models\WebhookLog;
use Illuminate\Console\Scheduling\Schedule;

// docs/05-data-safety.md: message logs are kept 12 months, webhook payloads 90 days, and a daily job removes the rest.

it('deletes message logs older than twelve months in every church and keeps newer ones', function (): void {
    $first = Church::factory()->create();
    $second = Church::factory()->create();
    $old = [
        MessageLog::factory()->create(['church_id' => $first->id, 'created_at' => now()->subMonths(12)->subDay()]),
        MessageLog::factory()->create(['church_id' => $second->id, 'created_at' => now()->subMonths(13)]),
    ];
    $kept = MessageLog::factory()->create(['church_id' => $first->id, 'created_at' => now()->subMonths(11)]);

    $this->artisan('model:prune', ['--model' => MessageLog::class])->assertSuccessful();

    expect(MessageLog::query()->withoutGlobalScopes()->pluck('id')->all())->toBe([$kept->id])
        ->and(MessageLog::query()->withoutGlobalScopes()->whereIn('id', array_map(fn ($log) => $log->id, $old))->count())->toBe(0);
});

it('deletes webhook payloads older than ninety days and keeps newer ones', function (): void {
    WebhookLog::factory()->create(['created_at' => now()->subDays(91)]);
    $kept = WebhookLog::factory()->create(['created_at' => now()->subDays(89)]);

    $this->artisan('model:prune', ['--model' => WebhookLog::class])->assertSuccessful();

    expect(WebhookLog::query()->pluck('id')->all())->toBe([$kept->id]);
});

it('runs the prune every day at 03:00 UTC', function (): void {
    $event = collect(app(Schedule::class)->events())->first(fn ($event): bool => str_contains($event->command, 'model:prune'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 3 * * *')
        ->and($event->timezone)->toBe('UTC');
});
