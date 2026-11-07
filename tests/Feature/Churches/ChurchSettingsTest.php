<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\Church;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

// The church's settings (docs/11 story 5.14, docs/08 Church Settings, QA-10, D-055 point 6): the three choices the app already
// reads. Any staff member may read them; only the church admin (`church.settings`) changes them, and every change is written to
// the history.

const CHURCH_SETTINGS = 'http://grace.localhost/api/v1/church/settings';

/** A staff member of the church with one role. */
function settingsStaff(Church $church, string $role): User
{
    $user = User::factory()->for($church)->create();
    $user->assignRole($role);

    return $user;
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace', 'settings' => null]);
    $this->other = Church::factory()->create(['slug' => 'other', 'settings' => ['youth_age_limit' => 18]]);
    $this->admin = settingsStaff($this->church, 'church-admin');
    Sanctum::actingAs($this->admin, ['*']);
});

describe('reading', function (): void {
    it('gives the defaults for a church that has chosen nothing', function (): void {
        $this->getJson(CHURCH_SETTINGS)->assertOk()->assertExactJson(['data' => ['youth_age_limit' => 25, 'absence_threshold' => 3, 'receipt_on_cash' => true, 'absent_alert' => false]]);
    });

    it('gives the church\'s own choices over the defaults', function (): void {
        $this->church->update(['settings' => ['youth_age_limit' => 20, 'receipt_on_cash' => false]]);

        $this->getJson(CHURCH_SETTINGS)->assertOk()->assertExactJson(['data' => ['youth_age_limit' => 20, 'absence_threshold' => 3, 'receipt_on_cash' => false, 'absent_alert' => false]]);
    });

    it('is the signed-in church\'s own, never another church\'s (isolation)', function (): void {
        $this->getJson(CHURCH_SETTINGS)->assertOk()->assertJsonPath('data.youth_age_limit', 25);
    });
});

describe('changing', function (): void {
    it('saves what is sent and answers with every setting', function (): void {
        $this->putJson(CHURCH_SETTINGS, ['settings' => ['youth_age_limit' => 30, 'absence_threshold' => 4, 'receipt_on_cash' => false]])->assertOk()
            ->assertExactJson(['data' => ['youth_age_limit' => 30, 'absence_threshold' => 4, 'receipt_on_cash' => false, 'absent_alert' => false]]);

        $church = $this->church->fresh();
        expect($church->youthAgeLimit())->toBe(30)->and($church->absenceThreshold())->toBe(4)->and($church->sendsCashReceipts())->toBeFalse();
    });

    it('changes only what is sent and keeps the rest, including a setting this version does not know', function (): void {
        $this->church->update(['settings' => ['youth_age_limit' => 20, 'receipt_on_cash' => false, 'future_setting' => 'kept']]);

        $this->putJson(CHURCH_SETTINGS, ['settings' => ['absence_threshold' => 5]])->assertOk();

        // The database keeps the settings in its own key order, so they are compared as a set.
        expect($this->church->fresh()->settings)->toEqual(['youth_age_limit' => 20, 'receipt_on_cash' => false, 'future_setting' => 'kept', 'absence_threshold' => 5]);
    });

    it('does not touch another church', function (): void {
        $this->putJson(CHURCH_SETTINGS, ['settings' => ['youth_age_limit' => 30]])->assertOk();

        expect($this->other->fresh()->settings)->toBe(['youth_age_limit' => 18]);
    });

    it('writes the old and new values to the history, and nothing when nothing changed', function (): void {
        $this->church->update(['settings' => ['youth_age_limit' => 20]]);

        $this->putJson(CHURCH_SETTINGS, ['settings' => ['youth_age_limit' => 20, 'receipt_on_cash' => false]])->assertOk();
        $this->putJson(CHURCH_SETTINGS, ['settings' => ['youth_age_limit' => 20, 'receipt_on_cash' => false]])->assertOk();

        $line = AuditLog::query()->where('auditable_id', $this->church->id)->sole();
        expect($line->event)->toBe(AuditEvent::Updated)->and($line->actor_id)->toBe($this->admin->id)
            ->and($line->old_values)->toBe(['receipt_on_cash' => true])->and($line->new_values)->toBe(['receipt_on_cash' => false]);
    });

    it('switches the automatic absence text on and off, and it starts off', function (): void {
        $this->putJson(CHURCH_SETTINGS, ['settings' => ['absent_alert' => true]])->assertOk()->assertJsonPath('data.absent_alert', true);
        expect($this->church->fresh()->sendsAbsenceAlerts())->toBeTrue();

        $this->putJson(CHURCH_SETTINGS, ['settings' => ['absent_alert' => false]])->assertOk()->assertJsonPath('data.absent_alert', false);
        expect($this->church->fresh()->sendsAbsenceAlerts())->toBeFalse();
    });

    it('takes a setting back to the default by sending the default, which is saved like any other', function (): void {
        $this->putJson(CHURCH_SETTINGS, ['settings' => ['absence_threshold' => 6]])->assertOk();
        $this->putJson(CHURCH_SETTINGS, ['settings' => ['absence_threshold' => 3]])->assertOk()->assertJsonPath('data.absence_threshold', 3);
    });
});

describe('validation', function (): void {
    it('asks for the settings, and not an empty set', function (): void {
        $this->putJson(CHURCH_SETTINGS, [])->assertStatus(422)->assertJsonValidationErrors('settings');
        $this->putJson(CHURCH_SETTINGS, ['settings' => []])->assertStatus(422)->assertJsonValidationErrors('settings');
    });

    it('refuses a setting that does not exist, so a typo is not silently ignored', function (): void {
        $this->putJson(CHURCH_SETTINGS, ['settings' => ['youth_age' => 20]])->assertStatus(422)->assertJsonValidationErrors('settings');
        expect($this->church->fresh()->settings)->toBeNull();
    });

    it('keeps each setting inside its range', function (): void {
        $this->putJson(CHURCH_SETTINGS, ['settings' => ['youth_age_limit' => 9]])->assertStatus(422)->assertJsonValidationErrors('settings.youth_age_limit');
        $this->putJson(CHURCH_SETTINGS, ['settings' => ['youth_age_limit' => 41]])->assertStatus(422)->assertJsonValidationErrors('settings.youth_age_limit');
        $this->putJson(CHURCH_SETTINGS, ['settings' => ['absence_threshold' => 1]])->assertStatus(422)->assertJsonValidationErrors('settings.absence_threshold');
        $this->putJson(CHURCH_SETTINGS, ['settings' => ['absence_threshold' => 13]])->assertStatus(422)->assertJsonValidationErrors('settings.absence_threshold');
        $this->putJson(CHURCH_SETTINGS, ['settings' => ['receipt_on_cash' => 'sometimes']])->assertStatus(422)->assertJsonValidationErrors('settings.receipt_on_cash');
        $this->putJson(CHURCH_SETTINGS, ['settings' => ['youth_age_limit' => 'young']])->assertStatus(422)->assertJsonValidationErrors('settings.youth_age_limit');
    });

    it('accepts the edges of each range', function (): void {
        $this->putJson(CHURCH_SETTINGS, ['settings' => ['youth_age_limit' => 10, 'absence_threshold' => 2]])->assertOk();
        $this->putJson(CHURCH_SETTINGS, ['settings' => ['youth_age_limit' => 40, 'absence_threshold' => 12]])->assertOk();
    });
});

describe('who may', function (): void {
    it('refuses a visitor with no sign-in', function (): void {
        $this->app['auth']->forgetGuards();

        $this->getJson(CHURCH_SETTINGS)->assertUnauthorized();
        $this->putJson(CHURCH_SETTINGS, ['settings' => ['youth_age_limit' => 30]])->assertUnauthorized();
    });

    it('lets every role read the settings', function (string $role): void {
        Sanctum::actingAs(settingsStaff($this->church, $role), ['*']);

        $this->getJson(CHURCH_SETTINGS)->assertOk();
    })->with(['church-admin', 'pastor', 'finance-secretary', 'department-head', 'youth-pastor']);

    it('lets only the church admin change them', function (string $role): void {
        Sanctum::actingAs(settingsStaff($this->church, $role), ['*']);

        $this->putJson(CHURCH_SETTINGS, ['settings' => ['youth_age_limit' => 30]])->assertForbidden()->assertJsonPath('message', 'This action is unauthorized.');
        expect($this->church->fresh()->settings)->toBeNull();
    })->with(['pastor', 'finance-secretary', 'department-head', 'youth-pastor']);
});

describe('the policy', function (): void {
    it('never lets anyone read or change the settings of a church they do not belong to', function (): void {
        $admin = settingsStaff($this->church, 'church-admin');

        expect($admin->can('view', $this->church))->toBeTrue()->and($admin->can('view', $this->other))->toBeFalse()
            ->and($admin->can('update', $this->church))->toBeTrue()->and($admin->can('update', $this->other))->toBeFalse();
    });
});
