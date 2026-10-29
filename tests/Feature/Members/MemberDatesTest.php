<?php

declare(strict_types=1);

use App\Models\Church;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

// Dates on a member (docs/03, validation): a birth date, a "member since" date and a milestone date cannot be in the
// future, a birth date cannot be before 1900, and nobody joins or reaches a milestone before they were born.

const DATE_MEMBERS = 'http://grace.localhost/api/v1/members';

/** A valid new member body, with the given changes. */
function dateMemberBody(array $overrides = []): array
{
    return ['first_name' => 'Abena', 'last_name' => 'Asante', 'gender' => 'female', 'phone' => '0200987654', ...$overrides];
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace', 'member_number_prefix' => 'GRC']);
    $this->admin = User::factory()->for($this->church)->create();
    $this->admin->assignRole('church-admin');
    Sanctum::actingAs($this->admin, ['*']);
});

describe('date of birth', function (): void {
    it('takes today and any day back to 1900', function (string $date): void {
        $this->postJson(DATE_MEMBERS, dateMemberBody(['date_of_birth' => $date]))->assertCreated();
    })->with(['today' => fn () => now()->toDateString(), 'the first day allowed' => '1900-01-01', 'an ordinary birthday' => '1985-03-14']);

    it('refuses the future and the time before 1900', function (string $date): void {
        $this->postJson(DATE_MEMBERS, dateMemberBody(['date_of_birth' => $date]))->assertUnprocessable()->assertJsonValidationErrors('date_of_birth');
    })->with(['tomorrow' => fn () => now()->addDay()->toDateString(), 'far ahead' => '2099-01-01', 'a typo year' => '1899-12-31', 'year 1' => '0001-01-01']);
});

describe('member since', function (): void {
    it('takes today and any earlier day', function (): void {
        $this->postJson(DATE_MEMBERS, dateMemberBody(['membership_date' => now()->toDateString()]))->assertCreated();
        $this->postJson(DATE_MEMBERS, dateMemberBody(['phone' => '0200987655', 'membership_date' => '2001-06-01']))->assertCreated();
    });

    it('refuses a day in the future', function (): void {
        $this->postJson(DATE_MEMBERS, dateMemberBody(['membership_date' => now()->addDay()->toDateString()]))->assertUnprocessable()->assertJsonValidationErrors('membership_date');
    });

    it('refuses a day before the member was born, and takes the day they were born', function (): void {
        $this->postJson(DATE_MEMBERS, dateMemberBody(['date_of_birth' => '2000-05-10', 'membership_date' => '2000-05-09']))->assertUnprocessable()->assertJsonValidationErrors('membership_date');
        $this->postJson(DATE_MEMBERS, dateMemberBody(['date_of_birth' => '2000-05-10', 'membership_date' => '2000-05-10']))->assertCreated();
    });

    it('checks against the saved birth date when only the membership date is changed', function (): void {
        $member = Member::factory()->create(['church_id' => $this->church->id, 'date_of_birth' => '2000-05-10']);

        $this->putJson(DATE_MEMBERS.'/'.$member->id, ['membership_date' => '1999-01-01'])->assertUnprocessable()->assertJsonValidationErrors('membership_date');
        $this->putJson(DATE_MEMBERS.'/'.$member->id, ['membership_date' => '2010-01-01'])->assertOk();
    });

    it('does not compare with a birth date that is not known', function (): void {
        $member = Member::factory()->create(['church_id' => $this->church->id, 'date_of_birth' => null]);

        $this->putJson(DATE_MEMBERS.'/'.$member->id, ['membership_date' => '1950-01-01'])->assertOk();
    });

    it('uses the new birth date when both are sent together', function (): void {
        $member = Member::factory()->create(['church_id' => $this->church->id, 'date_of_birth' => '2000-05-10']);

        $this->putJson(DATE_MEMBERS.'/'.$member->id, ['date_of_birth' => '1990-01-01', 'membership_date' => '1995-01-01'])->assertOk();
    });
});

describe('a milestone date', function (): void {
    it('cannot be before the member was born, but can be the day they were born', function (): void {
        $member = Member::factory()->create(['church_id' => $this->church->id, 'date_of_birth' => '2000-05-10']);
        $url = DATE_MEMBERS.'/'.$member->id.'/milestones';

        $this->postJson($url, ['type' => 'baptism', 'occurred_at' => '2000-05-09'])->assertUnprocessable()->assertJsonValidationErrors('occurred_at');
        $this->postJson($url, ['type' => 'baptism', 'occurred_at' => '2000-05-10'])->assertCreated();
    });

    it('cannot be in the future', function (): void {
        $member = Member::factory()->create(['church_id' => $this->church->id]);

        $this->postJson(DATE_MEMBERS.'/'.$member->id.'/milestones', ['type' => 'baptism', 'occurred_at' => now()->addDay()->toDateString()])->assertUnprocessable()->assertJsonValidationErrors('occurred_at');
    });

    it('has nothing to compare with when the birth date is not known', function (): void {
        $member = Member::factory()->create(['church_id' => $this->church->id, 'date_of_birth' => null]);

        $this->postJson(DATE_MEMBERS.'/'.$member->id.'/milestones', ['type' => 'baptism', 'occurred_at' => '1960-01-01'])->assertCreated();
    });
});
