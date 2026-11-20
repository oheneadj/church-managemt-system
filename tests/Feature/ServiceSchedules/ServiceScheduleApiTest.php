<?php

declare(strict_types=1);

use App\Enums\Weekday;
use App\Models\AttendanceRecord;
use App\Models\Church;
use App\Models\Contribution;
use App\Models\Member;
use App\Models\Service;
use App\Models\ServiceSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

// Weekly schedules (D-050): set up once, the services are made ahead of time. A Wednesday is "today" in these tests
// (4 Nov 2026), so the first Sunday is the 8th and the eight services run to 27 Dec.

const SCHEDULES = 'http://grace.localhost/api/v1/service-schedules';

/** A staff member of the church with one role. */
function scheduleStaff(Church $church, string $role): User
{
    $user = User::factory()->for($church)->create();
    $user->assignRole($role);

    return $user;
}

/** A valid schedule body. */
function scheduleBody(array $overrides = []): array
{
    return ['name' => 'Sunday Morning Service', 'type' => 'sunday', 'weekday' => 'sunday', 'starts_at' => '09:00', 'ends_at' => '11:30', ...$overrides];
}

/** Sets a schedule up with a fresh Idempotency-Key unless one is given. */
function setUpSchedule(array $body = [], ?string $key = null): TestResponse
{
    return test()->postJson(SCHEDULES, scheduleBody($body), ['Idempotency-Key' => $key ?? 'schedule-'.Str::random(20)]);
}

/** The dates of the schedule's services, oldest first. */
function scheduledDates(string $scheduleId): array
{
    return Service::query()->where('service_schedule_id', $scheduleId)->orderBy('service_date')->get()->map(fn (Service $s) => $s->service_date->toDateString())->all();
}

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-04 10:00:00', 'UTC'));
    $this->seed(RolesAndPermissionsSeeder::class);
    Cache::flush();
    $this->church = Church::factory()->create(['slug' => 'grace']);
    $this->admin = scheduleStaff($this->church, 'church-admin');
    $this->other = Church::factory()->create(['slug' => 'other']);
    Sanctum::actingAs($this->admin, ['*']);
});

describe('setting up a schedule', function (): void {
    it('saves it and makes the next eight Sundays at once', function (): void {
        $response = setUpSchedule()->assertCreated();

        $response->assertJsonPath('data.name', 'Sunday Morning Service')->assertJsonPath('data.weekday', 'sunday')->assertJsonPath('data.weekday_label', 'Sunday')
            ->assertJsonPath('data.type_label', 'Sunday')->assertJsonPath('data.starts_at', '09:00')->assertJsonPath('data.ends_at', '11:30')->assertJsonPath('data.is_active', true);
        expect($response->json('data.id'))->toMatch('/^[0-9a-z]{26}$/')
            ->and(scheduledDates($response->json('data.id')))->toBe(['2026-11-08', '2026-11-15', '2026-11-22', '2026-11-29', '2026-12-06', '2026-12-13', '2026-12-20', '2026-12-27']);
    });

    it('makes services that are ordinary in every other way', function (): void {
        $scheduleId = setUpSchedule()->json('data.id');

        $service = Service::query()->where('service_schedule_id', $scheduleId)->orderBy('service_date')->first();
        expect($service->name)->toBe('Sunday Morning Service')->and($service->starts_at)->toBe('09:00:00')->and($service->ends_at)->toBe('11:30:00')
            ->and($service->is_open)->toBeFalse()->and($service->church_id)->toBe($this->church->id)->and($service->created_by)->toBe($this->admin->id)
            ->and($service->checkin_code)->toMatch('/^['.preg_quote(config('attendance.checkin_code_alphabet'), '/').']{6}$/');
        expect(Service::query()->where('service_schedule_id', $scheduleId)->pluck('checkin_code')->unique())->toHaveCount(8);
        $this->getJson('http://grace.localhost/api/v1/services/'.$service->id)->assertOk()->assertJsonPath('data.schedule_id', $scheduleId);
    });

    it('starts today when today is the schedule\'s weekday', function (): void {
        $id = setUpSchedule(['weekday' => 'wednesday'])->json('data.id');

        expect(scheduledDates($id)[0])->toBe('2026-11-04')->and(scheduledDates($id)[7])->toBe('2026-12-23');
    });

    it('makes nothing for a schedule that is switched off', function (): void {
        $id = setUpSchedule(['is_active' => false])->assertCreated()->json('data.id');

        expect(scheduledDates($id))->toBe([]);
    });

    it('takes a schedule with no times', function (): void {
        setUpSchedule(['starts_at' => null, 'ends_at' => null])->assertCreated()->assertJsonPath('data.starts_at', null);
    });

    it('makes one schedule when the same key arrives twice', function (): void {
        setUpSchedule([], 'same-schedule-key')->assertCreated();
        setUpSchedule([], 'same-schedule-key')->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

        expect(ServiceSchedule::query()->count())->toBe(1)->and(Service::query()->count())->toBe(8);
        $this->postJson(SCHEDULES, scheduleBody())->assertStatus(400);
    });

    it('rejects bad values and makes nothing', function (array $bad, string $field): void {
        setUpSchedule($bad)->assertUnprocessable()->assertJsonValidationErrors($field);

        expect(ServiceSchedule::query()->count())->toBe(0)->and(Service::query()->count())->toBe(0);
    })->with([
        'no name' => [['name' => ''], 'name'],
        'unknown type' => [['type' => 'party'], 'type'],
        'no weekday' => [['weekday' => null], 'weekday'],
        'unknown weekday' => [['weekday' => 'someday'], 'weekday'],
        'bad time' => [['starts_at' => '9am'], 'starts_at'],
        'ends before it starts' => [['starts_at' => '11:00', 'ends_at' => '09:00'], 'ends_at'],
    ]);
});

describe('listing', function (): void {
    it('lists the church\'s schedules by name, in pages, and filters by on or off', function (): void {
        ServiceSchedule::factory()->create(['church_id' => $this->church->id, 'name' => 'Youth Meeting', 'weekday' => Weekday::Friday]);
        ServiceSchedule::factory()->inactive()->create(['church_id' => $this->church->id, 'name' => 'Choir Practice']);
        ServiceSchedule::factory()->create(['church_id' => $this->other->id, 'name' => 'Theirs']);

        $all = $this->getJson(SCHEDULES)->assertOk();
        expect(collect($all->json('data'))->pluck('name')->all())->toBe(['Choir Practice', 'Youth Meeting'])->and($all->json('meta.total'))->toBe(2)
            ->and($all->json('data.0'))->not->toHaveKey('church_id');
        expect(collect($this->getJson(SCHEDULES.'?is_active=1')->json('data'))->pluck('name')->all())->toBe(['Youth Meeting']);
        $this->getJson(SCHEDULES.'?per_page=0')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    });
});

describe('changing a schedule', function (): void {
    beforeEach(function (): void {
        $this->id = setUpSchedule()->json('data.id');
        $this->schedule = ServiceSchedule::query()->find($this->id);
        $this->services = fn () => Service::query()->where('service_schedule_id', $this->id)->orderBy('service_date')->get();
    });

    it('carries a new name and new times to the future services', function (): void {
        $this->putJson(SCHEDULES.'/'.$this->id, ['name' => 'Worship Service', 'starts_at' => '08:30', 'ends_at' => '10:30'])
            ->assertOk()->assertJsonPath('data.name', 'Worship Service')->assertJsonPath('data.starts_at', '08:30');

        expect(($this->services)()->pluck('name')->unique()->all())->toBe(['Worship Service'])
            ->and(($this->services)()->pluck('starts_at')->unique()->all())->toBe(['08:30:00'])
            ->and(($this->services)()->pluck('ends_at')->unique()->all())->toBe(['10:30:00']);
    });

    it('leaves a service renamed by hand with its own name, and changes the rest', function (): void {
        $mine = ($this->services)()->get(2);
        $mine->update(['name' => 'Harvest Service']);

        $this->putJson(SCHEDULES.'/'.$this->id, ['name' => 'Worship Service'])->assertOk();

        expect($mine->fresh()->name)->toBe('Harvest Service')->and(($this->services)()->where('id', '!=', $mine->id)->pluck('name')->unique()->all())->toBe(['Worship Service']);
    });

    it('does not touch a service that is open, has someone checked in, has a gift, or is in the past', function (string $case): void {
        $service = ($this->services)()->get(1);
        match ($case) {
            'open' => $service->update(['is_open' => true]),
            'opened before' => $service->update(['checkin_opened_at' => now()->subDay()]),
            'check-in' => AttendanceRecord::factory()->create(['church_id' => $this->church->id, 'service_id' => $service->id, 'member_id' => Member::factory()->create(['church_id' => $this->church->id])->id]),
            'gift' => Contribution::factory()->create(['church_id' => $this->church->id, 'service_id' => $service->id]),
            'past' => $service->update(['service_date' => now()->subWeek()->toDateString()]),
        };

        $this->putJson(SCHEDULES.'/'.$this->id, ['name' => 'Worship Service'])->assertOk();

        expect($service->fresh()->name)->toBe('Sunday Morning Service')->and(($this->services)()->where('id', '!=', $service->id)->pluck('name')->unique()->all())->toBe(['Worship Service']);
    })->with(['open', 'opened before', 'check-in', 'gift', 'past']);

    it('moves the future services to a new weekday, keeping each in its own week', function (): void {
        $this->putJson(SCHEDULES.'/'.$this->id, ['weekday' => 'wednesday'])->assertOk()->assertJsonPath('data.weekday', 'wednesday');

        // Each Sunday service moved to the Wednesday after it; today is a Wednesday, so that day's service is made too.
        expect(scheduledDates($this->id))->toBe(['2026-11-04', '2026-11-11', '2026-11-18', '2026-11-25', '2026-12-02', '2026-12-09', '2026-12-16', '2026-12-23', '2026-12-30']);
    });

    it('fills the weeks ahead after a change, never making a date twice', function (): void {
        $this->putJson(SCHEDULES.'/'.$this->id, ['name' => 'Worship Service'])->assertOk();

        expect(Service::query()->where('service_schedule_id', $this->id)->count())->toBe(8);
    });

    it('stops making new services when turned off, keeps the ones already made, and picks up when turned on', function (): void {
        $this->putJson(SCHEDULES.'/'.$this->id, ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
        expect(Service::query()->where('service_schedule_id', $this->id)->count())->toBe(8);

        $this->travelTo(CarbonImmutable::parse('2026-11-18 10:00:00', 'UTC'));
        $this->artisan('services:generate-scheduled')->expectsOutputToContain('Made 0 service(s).')->assertSuccessful();
        expect(Service::query()->where('service_schedule_id', $this->id)->count())->toBe(8);

        $this->putJson(SCHEDULES.'/'.$this->id, ['is_active' => true])->assertOk();
        expect(Service::query()->where('service_schedule_id', $this->id)->count())->toBe(10)
            ->and(collect(scheduledDates($this->id))->last())->toBe('2027-01-10');
    });

    it('changes nothing it was not asked to', function (): void {
        $before = ($this->services)()->pluck('name', 'id');

        $this->putJson(SCHEDULES.'/'.$this->id, [])->assertOk();

        expect(($this->services)()->pluck('name', 'id')->all())->toBe($before->all());
    });

    it('rejects bad values', function (array $bad, string $field): void {
        $this->putJson(SCHEDULES.'/'.$this->id, $bad)->assertUnprocessable()->assertJsonValidationErrors($field);
    })->with([[['name' => ''], 'name'], [['weekday' => 'someday'], 'weekday'], [['starts_at' => '11:00', 'ends_at' => '09:00'], 'ends_at'], [['is_active' => 'maybe'], 'is_active']]);
});

describe('authentication and roles', function (): void {
    beforeEach(fn () => $this->schedule = ServiceSchedule::factory()->create(['church_id' => $this->church->id]));

    it('rejects every endpoint without a token', function (string $method, string $path): void {
        $this->app['auth']->forgetGuards();

        $this->json($method, str_replace('{id}', $this->schedule->id, SCHEDULES.$path))->assertUnauthorized();
    })->with([['GET', ''], ['POST', ''], ['PUT', '/{id}']]);

    it('lets only the church admin and the pastor see and change schedules, like creating services', function (string $role, bool $allowed): void {
        Sanctum::actingAs(scheduleStaff($this->church, $role), ['*']);

        $this->getJson(SCHEDULES)->assertStatus($allowed ? 200 : 403);
        setUpSchedule()->assertStatus($allowed ? 201 : 403);
        $this->putJson(SCHEDULES.'/'.$this->schedule->id, ['name' => 'Changed'])->assertStatus($allowed ? 200 : 403);
    })->with([['church-admin', true], ['pastor', true], ['finance-secretary', false], ['department-head', false], ['youth-pastor', false]]);
});

describe('another church (isolation)', function (): void {
    it('is a 404 to change a schedule of another church, and nothing changes', function (): void {
        $stranger = ServiceSchedule::factory()->create(['church_id' => $this->other->id, 'name' => 'Theirs']);

        $this->putJson(SCHEDULES.'/'.$stranger->id, ['name' => 'Mine now'])->assertNotFound();
        expect(ServiceSchedule::query()->withoutGlobalScopes()->find($stranger->id)->name)->toBe('Theirs');
    });

    it('is refused by the policy itself for a schedule of another church', function (): void {
        $stranger = ServiceSchedule::factory()->create(['church_id' => $this->other->id]);

        expect(Gate::forUser($this->admin)->allows('update', $stranger))->toBeFalse();
    });

    it('makes services for the church of the person who set it up', function (): void {
        $id = setUpSchedule()->json('data.id');

        expect(Service::query()->withoutGlobalScopes()->where('service_schedule_id', $id)->pluck('church_id')->unique()->all())->toBe([$this->church->id]);
    });
});
