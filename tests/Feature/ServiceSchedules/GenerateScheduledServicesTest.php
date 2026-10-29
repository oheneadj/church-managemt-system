<?php

declare(strict_types=1);

use App\Enums\Weekday;
use App\Models\Church;
use App\Models\Service;
use App\Models\ServiceSchedule;
use App\Services\ScheduledServiceGenerator;
use App\Services\ServiceCreator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;

// The daily job that keeps the weeks ahead filled in (D-050). A Wednesday is "today": 4 November 2026.

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-04 05:00:00', 'UTC'));
    $this->church = Church::factory()->create(['slug' => 'grace']);
    $this->other = Church::factory()->create(['slug' => 'other']);
});

/** The dates of a schedule's services, cancelled ones left out. */
function liveDates(ServiceSchedule $schedule): array
{
    return Service::query()->withoutGlobalScopes()->whereNull('deleted_at')->where('service_schedule_id', $schedule->id)->orderBy('service_date')->get()
        ->map(fn (Service $s) => $s->service_date->toDateString())->all();
}

it('makes the next eight weeks of services for a schedule that has none', function (): void {
    $schedule = ServiceSchedule::factory()->create(['church_id' => $this->church->id]);

    $this->artisan('services:generate-scheduled')->expectsOutputToContain('Made 8 service(s).')->assertSuccessful();

    expect(liveDates($schedule))->toBe(['2026-11-08', '2026-11-15', '2026-11-22', '2026-11-29', '2026-12-06', '2026-12-13', '2026-12-20', '2026-12-27']);
});

it('makes nothing more when run again', function (): void {
    ServiceSchedule::factory()->create(['church_id' => $this->church->id]);
    $this->artisan('services:generate-scheduled')->assertSuccessful();

    $this->artisan('services:generate-scheduled')->expectsOutputToContain('Made 0 service(s).')->assertSuccessful();

    expect(Service::query()->withoutGlobalScopes()->count())->toBe(8);
});

it('adds one more week each week, so the calendar stays eight weeks ahead', function (): void {
    $schedule = ServiceSchedule::factory()->create(['church_id' => $this->church->id]);
    $this->artisan('services:generate-scheduled')->assertSuccessful();

    $this->travelTo(CarbonImmutable::parse('2026-11-11 05:00:00', 'UTC'));
    $this->artisan('services:generate-scheduled')->expectsOutputToContain('Made 1 service(s).')->assertSuccessful();

    expect(collect(liveDates($schedule))->last())->toBe('2027-01-03')->and(liveDates($schedule))->toHaveCount(9);
});

it('works for every church, each as itself, and leaves no church in context', function (): void {
    $mine = ServiceSchedule::factory()->create(['church_id' => $this->church->id, 'name' => 'Ours']);
    $theirs = ServiceSchedule::factory()->create(['church_id' => $this->other->id, 'name' => 'Theirs', 'weekday' => Weekday::Friday]);

    $this->artisan('services:generate-scheduled')->expectsOutputToContain('Made 16 service(s).')->assertSuccessful();

    expect(Service::query()->withoutGlobalScopes()->where('service_schedule_id', $mine->id)->pluck('church_id')->unique()->all())->toBe([$this->church->id])
        ->and(Service::query()->withoutGlobalScopes()->where('service_schedule_id', $theirs->id)->pluck('church_id')->unique()->all())->toBe([$this->other->id])
        ->and(liveDates($theirs)[0])->toBe('2026-11-06')
        ->and(app()->bound('current.church'))->toBeFalse();
});

it('skips a schedule that is switched off', function (): void {
    ServiceSchedule::factory()->inactive()->create(['church_id' => $this->church->id]);

    $this->artisan('services:generate-scheduled')->expectsOutputToContain('Made 0 service(s).')->assertSuccessful();
});

it('never makes a date again that was cancelled', function (): void {
    $schedule = ServiceSchedule::factory()->create(['church_id' => $this->church->id]);
    $this->artisan('services:generate-scheduled')->assertSuccessful();
    $cancelled = Service::query()->where('service_schedule_id', $schedule->id)->whereDate('service_date', '2026-11-22')->sole();
    $cancelled->delete();

    $this->artisan('services:generate-scheduled')->expectsOutputToContain('Made 0 service(s).')->assertSuccessful();

    expect(liveDates($schedule))->not->toContain('2026-11-22')->and(liveDates($schedule))->toHaveCount(7);
});

it('fills in a date that is missing for any other reason', function (): void {
    $schedule = ServiceSchedule::factory()->create(['church_id' => $this->church->id]);
    $this->artisan('services:generate-scheduled')->assertSuccessful();
    Service::query()->where('service_schedule_id', $schedule->id)->whereDate('service_date', '2026-11-22')->sole()->forceDelete();

    $this->artisan('services:generate-scheduled')->expectsOutputToContain('Made 1 service(s).')->assertSuccessful();

    expect(liveDates($schedule))->toContain('2026-11-22');
});

it('leaves services made by hand on the same day alone, and makes its own', function (): void {
    $schedule = ServiceSchedule::factory()->create(['church_id' => $this->church->id]);
    Service::factory()->create(['church_id' => $this->church->id, 'service_date' => '2026-11-08', 'name' => 'By hand']);

    $this->artisan('services:generate-scheduled')->expectsOutputToContain('Made 8 service(s).')->assertSuccessful();

    expect(Service::query()->withoutGlobalScopes()->whereDate('service_date', '2026-11-08')->count())->toBe(2)->and(liveDates($schedule))->toHaveCount(8);
});

it('uses the church\'s own calendar to decide what today is', function (): void {
    // 05:00 UTC on Wednesday 4 Nov is already 14:00 there; make it 23:30 UTC so Tokyo is on Thursday 5 Nov.
    $this->travelTo(CarbonImmutable::parse('2026-11-04 23:30:00', 'UTC'));
    $this->church->update(['timezone' => 'Asia/Tokyo']);
    $schedule = ServiceSchedule::factory()->create(['church_id' => $this->church->id, 'weekday' => Weekday::Thursday]);

    $this->artisan('services:generate-scheduled')->assertSuccessful();

    expect(liveDates($schedule)[0])->toBe('2026-11-05');
});

it('runs every day at 05:00 UTC', function (): void {
    $event = collect(app(Schedule::class)->events())->first(fn ($event): bool => str_contains($event->command, 'services:generate-scheduled'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('0 5 * * *')->and($event->timezone)->toBe('UTC');
});

describe('the database guarantee', function (): void {
    it('refuses a second service for the same schedule and date, even when the first was cancelled', function (): void {
        $schedule = ServiceSchedule::factory()->create(['church_id' => $this->church->id]);
        $first = Service::factory()->create(['church_id' => $this->church->id, 'service_schedule_id' => $schedule->id, 'service_date' => '2026-11-08']);

        // Each try runs in its own nested transaction, because Postgres cancels the surrounding one after a failed statement.
        $again = fn () => DB::transaction(fn () => Service::factory()->create(['church_id' => $this->church->id, 'service_schedule_id' => $schedule->id, 'service_date' => '2026-11-08']));

        expect($again)->toThrow(UniqueConstraintViolationException::class);

        $first->delete();
        expect($again)->toThrow(UniqueConstraintViolationException::class);
    });

    it('lets two schedules, or a service made by hand, share a date', function (): void {
        $a = ServiceSchedule::factory()->create(['church_id' => $this->church->id]);
        $b = ServiceSchedule::factory()->create(['church_id' => $this->church->id]);

        Service::factory()->create(['church_id' => $this->church->id, 'service_schedule_id' => $a->id, 'service_date' => '2026-11-08']);
        Service::factory()->create(['church_id' => $this->church->id, 'service_schedule_id' => $b->id, 'service_date' => '2026-11-08']);
        Service::factory()->count(2)->create(['church_id' => $this->church->id, 'service_schedule_id' => null, 'service_date' => '2026-11-08']);

        expect(Service::query()->count())->toBe(4);
    });
});

describe('when things go wrong', function (): void {
    it('carries on when another run has just made a date', function (): void {
        $schedule = ServiceSchedule::factory()->create(['church_id' => $this->church->id]);
        $calls = 0;
        $creator = Mockery::mock(ServiceCreator::class);
        $creator->shouldReceive('create')->times(8)->andReturnUsing(function () use (&$calls): Service {
            if (++$calls === 3) {
                throw new UniqueConstraintViolationException('pgsql', 'insert', [], new PDOException('duplicate'));
            }

            return new Service;
        });
        app()->instance(ServiceCreator::class, $creator);

        $this->artisan('services:generate-scheduled')->expectsOutputToContain('Made 7 service(s).')->assertSuccessful();
    });

    it('reports a schedule that fails and still does the others', function (): void {
        Exceptions::fake();
        $first = ServiceSchedule::factory()->create(['church_id' => $this->church->id, 'name' => 'A first']);
        ServiceSchedule::factory()->create(['church_id' => $this->other->id, 'name' => 'B second']);
        $generator = Mockery::mock(ScheduledServiceGenerator::class);
        $generator->shouldReceive('generate')->twice()->andReturnUsing(fn (ServiceSchedule $s): int => $s->id === $first->id ? throw new RuntimeException('boom') : 5);
        app()->instance(ScheduledServiceGenerator::class, $generator);

        $this->artisan('services:generate-scheduled')->expectsOutputToContain('Made 5 service(s).')->assertSuccessful();

        Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'boom');
        expect(app()->bound('current.church'))->toBeFalse();
    });
});
