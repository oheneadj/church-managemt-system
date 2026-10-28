<?php

declare(strict_types=1);

use App\Models\AttendanceRecord;
use App\Models\Church;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

// Services (docs/11 story 3.1, docs/08 Services & Attendance, QA-05 TC-05-01/02, QA-10 attendance matrix, QA-02 TC-02-08).

const SERVICES = 'http://grace.localhost/api/v1/services';

/** A staff member of the church with one role. */
function serviceStaff(Church $church, string $role): User
{
    $user = User::factory()->for($church)->create();
    $user->assignRole($role);

    return $user;
}

/** A valid new service body. */
function serviceBody(array $overrides = []): array
{
    return ['name' => 'Sunday Morning Service', 'type' => 'sunday', 'service_date' => '2026-10-25', 'starts_at' => '09:00', 'ends_at' => '11:30', ...$overrides];
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace']);
    $this->admin = serviceStaff($this->church, 'church-admin');
    $this->other = Church::factory()->create(['slug' => 'other']);
    Sanctum::actingAs($this->admin, ['*']);
});

describe('creating', function (): void {
    it('creates a closed service with a system-made check-in code', function (): void {
        $response = $this->postJson(SERVICES, serviceBody())->assertCreated();

        $response->assertJsonPath('data.name', 'Sunday Morning Service')
            ->assertJsonPath('data.type', 'sunday')
            ->assertJsonPath('data.type_label', 'Sunday')
            ->assertJsonPath('data.service_date', '2026-10-25')
            ->assertJsonPath('data.starts_at', '09:00')
            ->assertJsonPath('data.is_open', false)
            ->assertJsonPath('data.attendance_count', 0);
        expect($response->json('data.checkin_code'))->toMatch('/^['.preg_quote(config('attendance.checkin_code_alphabet'), '/').']{'.config('attendance.checkin_code_length').'}$/')
            ->and($response->json('data.id'))->toMatch('/^[0-9a-z]{26}$/');
    });

    it('ignores a check-in code sent by the caller', function (): void {
        $response = $this->postJson(SERVICES, serviceBody(['checkin_code' => 'SUN1025']))->assertCreated();

        expect($response->json('data.checkin_code'))->not->toBe('SUN1025');
    });

    it('gives every service a different code, even in different churches', function (): void {
        $first = $this->postJson(SERVICES, serviceBody())->json('data.checkin_code');
        Sanctum::actingAs(serviceStaff($this->other, 'church-admin'), ['*']);
        $second = $this->postJson('http://other.localhost/api/v1/services', serviceBody())->json('data.checkin_code');

        expect($first)->not->toBe($second);
    });

    it('lets the database refuse a code that another church already uses', function (): void {
        $taken = Service::factory()->create(['church_id' => $this->other->id, 'checkin_code' => 'ABC234']);

        expect(fn () => DB::transaction(fn () => Service::factory()->create(['church_id' => $this->church->id, 'checkin_code' => $taken->checkin_code])))
            ->toThrow(QueryException::class);
    });

    it('allows two services on the same day (QA-05 TC-05-13)', function (): void {
        $this->postJson(SERVICES, serviceBody(['starts_at' => '07:00', 'ends_at' => '08:30']))->assertCreated();
        $this->postJson(SERVICES, serviceBody(['starts_at' => '09:00']))->assertCreated();

        expect(Service::query()->count())->toBe(2);
    });

    it('rejects bad values', function (array $bad, string $field): void {
        $this->postJson(SERVICES, serviceBody($bad))->assertUnprocessable()->assertJsonValidationErrors($field);
    })->with([
        'no name' => [['name' => ''], 'name'],
        'unknown type' => [['type' => 'party'], 'type'],
        'date format' => [['service_date' => '25/10/2026'], 'service_date'],
        'time format' => [['starts_at' => '9am'], 'starts_at'],
        'ends before it starts' => [['starts_at' => '11:00', 'ends_at' => '09:00'], 'ends_at'],
    ]);

    it('requires the name, type and date', function (): void {
        $this->postJson(SERVICES, [])->assertUnprocessable()->assertJsonValidationErrors(['name', 'type', 'service_date']);
    });
});

describe('listing', function (): void {
    it('lists newest first with the attendance count, in pages', function (): void {
        $old = Service::factory()->create(['church_id' => $this->church->id, 'service_date' => '2026-10-04']);
        $new = Service::factory()->create(['church_id' => $this->church->id, 'service_date' => '2026-10-25']);
        AttendanceRecord::factory()->count(2)->create(['church_id' => $this->church->id, 'service_id' => $new->id]);

        $response = $this->getJson(SERVICES)->assertOk();

        expect($response->json('data.0.id'))->toBe($new->id)
            ->and($response->json('data.0.attendance_count'))->toBe(2)
            ->and($response->json('data.1.id'))->toBe($old->id)
            ->and($response->json('meta.total'))->toBe(2);
    });

    it('filters by date range, type and open state', function (): void {
        Service::factory()->create(['church_id' => $this->church->id, 'service_date' => '2026-09-27', 'type' => 'sunday']);
        $match = Service::factory()->open()->create(['church_id' => $this->church->id, 'service_date' => '2026-10-14', 'type' => 'midweek']);
        Service::factory()->create(['church_id' => $this->church->id, 'service_date' => '2026-11-02', 'type' => 'midweek']);

        $ids = fn (string $query) => collect($this->getJson(SERVICES.$query)->assertOk()->json('data'))->pluck('id')->all();

        expect($ids('?from=2026-10-01&to=2026-10-31'))->toBe([$match->id])
            ->and($ids('?type=midweek&is_open=1'))->toBe([$match->id])
            ->and($ids('?type=youth'))->toBe([]);
    });

    it('rejects an unknown type or a backwards date range', function (string $query, string $field): void {
        $this->getJson(SERVICES.$query)->assertUnprocessable()->assertJsonValidationErrors($field);
    })->with([['?type=party', 'type'], ['?from=2026-10-31&to=2026-10-01', 'to'], ['?per_page=0', 'per_page']]);

    it('shows only this church\'s services (QA-02 TC-02-08)', function (): void {
        Service::factory()->create(['church_id' => $this->other->id]);

        $this->getJson(SERVICES)->assertOk()->assertJsonPath('meta.total', 0);
    });
});

describe('showing and changing', function (): void {
    beforeEach(fn () => $this->service = Service::factory()->create(['church_id' => $this->church->id]));

    it('shows one service by its ulid', function (): void {
        $this->getJson(SERVICES.'/'.$this->service->id)->assertOk()->assertJsonPath('data.id', $this->service->id);
    });

    it('updates the fields that were sent, including a manual headcount', function (): void {
        $this->putJson(SERVICES.'/'.$this->service->id, ['name' => 'Early Service', 'headcount_override' => 143])
            ->assertOk()->assertJsonPath('data.name', 'Early Service')->assertJsonPath('data.headcount_override', 143);
    });

    it('opens and closes check-in, and doing it twice is harmless (QA-05 TC-05-02, TC-05-10)', function (): void {
        $this->patchJson(SERVICES.'/'.$this->service->id.'/checkin', ['is_open' => true])->assertOk()->assertJsonPath('data.is_open', true);
        $this->patchJson(SERVICES.'/'.$this->service->id.'/checkin', ['is_open' => true])->assertOk()->assertJsonPath('data.is_open', true);
        $this->patchJson(SERVICES.'/'.$this->service->id.'/checkin', ['is_open' => false])->assertOk()->assertJsonPath('data.is_open', false);
    });

    it('notes when check-in was opened, and keeps the first time when it is opened again', function (): void {
        $this->patchJson(SERVICES.'/'.$this->service->id.'/checkin', ['is_open' => true])->assertOk();
        $first = $this->service->fresh()->checkin_opened_at;
        expect($first)->not->toBeNull();

        $this->travel(5)->minutes();
        $this->patchJson(SERVICES.'/'.$this->service->id.'/checkin', ['is_open' => true])->assertOk();
        expect($this->service->fresh()->checkin_opened_at->equalTo($first))->toBeTrue();

        $this->patchJson(SERVICES.'/'.$this->service->id.'/checkin', ['is_open' => false])->assertOk();
        $this->travel(5)->minutes();
        $this->patchJson(SERVICES.'/'.$this->service->id.'/checkin', ['is_open' => true])->assertOk();
        expect($this->service->fresh()->checkin_opened_at->greaterThan($first))->toBeTrue();
    });

    it('needs is_open to be true or false', function (): void {
        $this->patchJson(SERVICES.'/'.$this->service->id.'/checkin', [])->assertUnprocessable()->assertJsonValidationErrors('is_open');
        $this->patchJson(SERVICES.'/'.$this->service->id.'/checkin', ['is_open' => 'maybe'])->assertUnprocessable();
    });

    it('rejects a negative headcount', function (): void {
        $this->putJson(SERVICES.'/'.$this->service->id, ['headcount_override' => -1])->assertUnprocessable()->assertJsonValidationErrors('headcount_override');
    });
});

describe('authentication and roles', function (): void {
    beforeEach(fn () => $this->service = Service::factory()->create(['church_id' => $this->church->id]));

    it('rejects every endpoint without a token', function (string $method, string $path): void {
        $this->app['auth']->forgetGuards();
        $this->json($method, str_replace('{id}', $this->service->id, SERVICES.$path))->assertUnauthorized();
    })->with([['GET', ''], ['POST', ''], ['GET', '/{id}'], ['PUT', '/{id}'], ['PATCH', '/{id}/checkin']]);

    it('lets every role read services (QA-10)', function (string $role): void {
        Sanctum::actingAs(serviceStaff($this->church, $role), ['*']);

        $this->getJson(SERVICES)->assertOk();
        $this->getJson(SERVICES.'/'.$this->service->id)->assertOk();
    })->with(['church-admin', 'pastor', 'finance-secretary', 'department-head', 'youth-pastor']);

    it('lets only the church admin and the pastor create, change, open and close (QA-10)', function (string $role, bool $allowed): void {
        Sanctum::actingAs(serviceStaff($this->church, $role), ['*']);
        $expect = $allowed ? 200 : 403;

        $this->postJson(SERVICES, serviceBody())->assertStatus($allowed ? 201 : 403);
        $this->putJson(SERVICES.'/'.$this->service->id, ['name' => 'X'])->assertStatus($expect);
        $this->patchJson(SERVICES.'/'.$this->service->id.'/checkin', ['is_open' => true])->assertStatus($expect);
    })->with([['church-admin', true], ['pastor', true], ['finance-secretary', false], ['department-head', false], ['youth-pastor', false]]);
});

describe('another church', function (): void {
    it('is a 404 to read, change or open a service of another church (QA-02)', function (): void {
        $stranger = Service::factory()->create(['church_id' => $this->other->id, 'name' => 'Theirs']);

        $this->getJson(SERVICES.'/'.$stranger->id)->assertNotFound();
        $this->putJson(SERVICES.'/'.$stranger->id, ['name' => 'Mine now'])->assertNotFound();
        $this->patchJson(SERVICES.'/'.$stranger->id.'/checkin', ['is_open' => true])->assertNotFound();
        expect(Service::query()->withoutGlobalScopes()->find($stranger->id))->name->toBe('Theirs')->is_open->toBeFalse();
    });
});

describe('forgotten open services', function (): void {
    it('closes a service still open after its day ended, in every church, and leaves today\'s alone', function (): void {
        $stale = Service::factory()->open()->create(['church_id' => $this->church->id, 'service_date' => now()->subDays(3)->toDateString()]);
        $staleOther = Service::factory()->open()->create(['church_id' => $this->other->id, 'service_date' => now()->subDays(3)->toDateString()]);
        $today = Service::factory()->open()->create(['church_id' => $this->church->id, 'service_date' => now()->toDateString()]);
        $closed = Service::factory()->create(['church_id' => $this->church->id, 'service_date' => now()->subDays(3)->toDateString()]);

        $this->artisan('services:close-stale')->expectsOutputToContain('Closed 2 service(s)')->assertSuccessful();

        expect(Service::query()->withoutGlobalScopes()->find($stale->id)->is_open)->toBeFalse()
            ->and(Service::query()->withoutGlobalScopes()->find($staleOther->id)->is_open)->toBeFalse()
            ->and(Service::query()->withoutGlobalScopes()->find($today->id)->is_open)->toBeTrue()
            ->and($closed->fresh()->is_open)->toBeFalse();
    });

    it('runs every hour in UTC', function (): void {
        $event = collect(app(Schedule::class)->events())->first(fn ($event): bool => str_contains($event->command, 'services:close-stale'));

        expect($event)->not->toBeNull()->and($event->expression)->toBe('0 * * * *')->and($event->timezone)->toBe('UTC');
    });
});
