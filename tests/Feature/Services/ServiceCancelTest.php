<?php

declare(strict_types=1);

use App\Models\AttendanceRecord;
use App\Models\Church;
use App\Models\Contribution;
use App\Models\Member;
use App\Models\Service;
use App\Models\User;
use App\Models\VisitorRecord;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;

// Cancelling a service (D-050): a closed service with nothing recorded at it is removed from the calendar (soft-deleted,
// never hard-deleted). Anything with attendance or gifts stays, because that history must stay reachable.

const CANCEL_SERVICES = 'http://grace.localhost/api/v1/services';

/** A staff member of the church with one role. */
function cancelStaff(Church $church, string $role): User
{
    $user = User::factory()->for($church)->create();
    $user->assignRole($role);

    return $user;
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace']);
    $this->admin = cancelStaff($this->church, 'church-admin');
    $this->other = Church::factory()->create(['slug' => 'other']);
    $this->service = Service::factory()->create(['church_id' => $this->church->id, 'service_date' => now()->addWeek()->toDateString(), 'checkin_code' => 'CAN3L5']);
    Sanctum::actingAs($this->admin, ['*']);
});

describe('cancelling', function (): void {
    it('removes a closed service with nothing recorded, and keeps the row', function (): void {
        $this->deleteJson(CANCEL_SERVICES.'/'.$this->service->id)->assertNoContent();

        expect(Service::query()->find($this->service->id))->toBeNull()
            ->and(Service::query()->withTrashed()->find($this->service->id)->deleted_at)->not->toBeNull();
        $this->getJson(CANCEL_SERVICES.'/'.$this->service->id)->assertNotFound();
        expect(collect($this->getJson(CANCEL_SERVICES)->assertOk()->json('data'))->pluck('id')->all())->not->toContain($this->service->id);
    });

    it('frees the check-in code for another service', function (): void {
        $this->deleteJson(CANCEL_SERVICES.'/'.$this->service->id)->assertNoContent();

        Service::factory()->create(['church_id' => $this->other->id, 'checkin_code' => 'CAN3L5']);

        expect(Service::query()->withoutGlobalScopes()->whereNull('deleted_at')->where('checkin_code', 'CAN3L5')->count())->toBe(1);
    });

    it('cannot be done twice', function (): void {
        $this->deleteJson(CANCEL_SERVICES.'/'.$this->service->id)->assertNoContent();

        $this->deleteJson(CANCEL_SERVICES.'/'.$this->service->id)->assertNotFound();
    });

    it('refuses a service that check-in is still open for', function (): void {
        $this->service->update(['is_open' => true]);

        $this->deleteJson(CANCEL_SERVICES.'/'.$this->service->id)->assertStatus(409)->assertJsonPath('message', 'Check-in is still open for this service. Close it before cancelling.');
        expect(Service::query()->find($this->service->id))->not->toBeNull();
    });

    it('refuses a service with a member checked in, a visitor, or a gift (even a voided one)', function (string $kind): void {
        match ($kind) {
            'member' => AttendanceRecord::factory()->create(['church_id' => $this->church->id, 'service_id' => $this->service->id, 'member_id' => Member::factory()->create(['church_id' => $this->church->id])->id]),
            'visitor' => AttendanceRecord::factory()->create(['church_id' => $this->church->id, 'service_id' => $this->service->id, 'member_id' => null, 'visitor_record_id' => VisitorRecord::factory()->create(['church_id' => $this->church->id])->id]),
            'gift' => Contribution::factory()->create(['church_id' => $this->church->id, 'service_id' => $this->service->id]),
            'voided gift' => Contribution::factory()->voided()->create(['church_id' => $this->church->id, 'service_id' => $this->service->id]),
        };

        $this->deleteJson(CANCEL_SERVICES.'/'.$this->service->id)->assertStatus(409)
            ->assertJsonPath('message', 'This service already has attendance or gifts recorded, so it cannot be cancelled. Close it instead.');
        expect(Service::query()->find($this->service->id))->not->toBeNull();
    })->with(['member', 'visitor', 'gift', 'voided gift']);
});

describe('authentication and roles', function (): void {
    it('needs a token', function (): void {
        $this->app['auth']->forgetGuards();

        $this->deleteJson(CANCEL_SERVICES.'/'.$this->service->id)->assertUnauthorized();
    });

    it('lets only the church admin and the pastor cancel, like creating a service', function (string $role, bool $allowed): void {
        Sanctum::actingAs(cancelStaff($this->church, $role), ['*']);

        $this->deleteJson(CANCEL_SERVICES.'/'.$this->service->id)->assertStatus($allowed ? 204 : 403);
    })->with([['church-admin', true], ['pastor', true], ['finance-secretary', false], ['department-head', false], ['youth-pastor', false]]);
});

describe('another church (isolation)', function (): void {
    it('is a 404 to cancel a service of another church, and nothing changes', function (): void {
        $stranger = Service::factory()->create(['church_id' => $this->other->id]);

        $this->deleteJson(CANCEL_SERVICES.'/'.$stranger->id)->assertNotFound();
        expect(Service::query()->withoutGlobalScopes()->find($stranger->id))->not->toBeNull();
    });

    it('is refused by the policy itself for a service of another church', function (): void {
        $stranger = Service::factory()->create(['church_id' => $this->other->id]);

        expect(Gate::forUser($this->admin)->allows('cancel', $stranger))->toBeFalse();
    });
});
