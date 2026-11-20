<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\Church;
use App\Models\Contribution;
use App\Models\FundType;
use App\Models\Member;
use App\Models\Pledge;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

// The recent activity feed (D-068, docs/02 Module 6): `GET /activity` turns the change history into plain sentences for the
// home page. It needs `reports.view`; each kind of line also needs the permission that guards the thing it is about, so a
// pastor never learns of an individual gift (D-046) and only the church admin sees staff and settings changes.

const ACTIVITY = 'http://grace.localhost/api/v1/activity';

/** A staff member of the church with one role. */
function activityStaff(Church $church, string $role, array $attributes = []): User
{
    $user = User::factory()->for($church)->create($attributes);
    $user->assignRole($role);

    return $user;
}

/** One line of the change history, written the way the real code writes it. */
function historyLine(Church $church, object $subject, AuditEvent $event, array $old = [], array $new = [], ?string $actor = 'Kofi Admin', ?string $reason = null, string $at = 'now'): AuditLog
{
    return AuditLog::factory()->create([
        'church_id' => $church->id,
        'auditable_type' => $subject->getMorphClass(),
        'auditable_id' => $subject->getKey(),
        'event' => $event,
        'old_values' => $old === [] ? null : $old,
        'new_values' => $new === [] ? null : $new,
        'changed_fields' => array_keys($new),
        'actor_name' => $actor,
        'reason' => $reason,
        'created_at' => CarbonImmutable::parse($at),
    ]);
}

/** The sentences the feed shows, newest first. */
function feedTexts(): array
{
    return collect(test()->getJson(ACTIVITY)->assertOk()->json('data'))->pluck('text')->all();
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace', 'name' => 'Grace Assembly']);
    $this->other = Church::factory()->create(['slug' => 'other']);
    $this->admin = activityStaff($this->church, 'church-admin', ['name' => 'Kofi Admin']);
    $this->member = Member::factory()->create(['church_id' => $this->church->id, 'first_name' => 'Kwame', 'last_name' => 'Boateng']);
    $this->fund = FundType::factory()->create(['church_id' => $this->church->id, 'name' => 'Building Fund']);
    $this->gift = Contribution::factory()->create(['church_id' => $this->church->id, 'fund_type_id' => $this->fund->id, 'amount' => '1250.50']);
    $this->pledge = Pledge::factory()->create(['church_id' => $this->church->id, 'fund_type_id' => $this->fund->id, 'amount_pledged' => '3000.00']);
    $this->staff = activityStaff($this->church, 'finance-secretary', ['name' => 'Esi Owusu', 'email' => 'esi@example.com', 'phone' => '+233244000111']);
    Sanctum::actingAs($this->admin, ['*']);
});

it('is refused without a token', function (): void {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson(ACTIVITY)->assertUnauthorized();
});

it('is refused to roles that may not read reports', function (string $role): void {
    Sanctum::actingAs(activityStaff($this->church, $role), ['*']);

    $this->getJson(ACTIVITY)->assertForbidden();
})->with(['department-head', 'youth-pastor']);

it('is open to the church admin, the pastor and the finance secretary', function (string $role): void {
    Sanctum::actingAs(activityStaff($this->church, $role), ['*']);

    $this->getJson(ACTIVITY)->assertOk()->assertJsonStructure(['data', 'meta' => ['total'], 'links']);
})->with(['church-admin', 'pastor', 'finance-secretary']);

describe('the words', function (): void {
    it('says in plain words what happened, who did it and when', function (): void {
        historyLine($this->church, $this->member, AuditEvent::Created, new: ['status' => 'active'], actor: 'Abena Pastor', at: '2026-11-09 10:00:00');

        $row = $this->getJson(ACTIVITY)->assertOk()->json('data.0');

        expect($row)->toMatchArray(['kind' => 'member_added', 'text' => 'Kwame Boateng was added as a member.', 'actor_name' => 'Abena Pastor'])
            ->and($row['occurred_at'])->toBe('2026-11-09T10:00:00+00:00')
            ->and($row)->toHaveKey('id');
    });

    it('says a member\'s status changed, with the new status in words', function (): void {
        historyLine($this->church, $this->member, AuditEvent::StatusChanged, ['status' => 'active'], ['status' => 'inactive'], reason: 'Moved away');

        expect(feedTexts())->toBe(['Kwame Boateng is now Inactive.']);
    });

    it('says a gift was recorded and voided, with its amount and fund and no name', function (): void {
        historyLine($this->church, $this->gift, AuditEvent::Created, new: ['amount' => '1250.50'], at: '2026-11-09 09:00:00');
        historyLine($this->church, $this->gift, AuditEvent::Voided, ['is_voided' => false], ['is_voided' => true], reason: 'Entered in error', at: '2026-11-09 11:00:00');

        expect(feedTexts())->toBe(['A gift of GHS 1,250.50 to Building Fund was voided.', 'A gift of GHS 1,250.50 to Building Fund was recorded.']);
    });

    it('says a pledge was made, with its amount and fund', function (): void {
        historyLine($this->church, $this->pledge, AuditEvent::Created, new: ['amount_pledged' => '3000.00']);

        expect(feedTexts())->toBe(['A pledge of GHS 3,000.00 to Building Fund was made.']);
    });

    it('says a staff member was invited, deactivated or reactivated', function (): void {
        historyLine($this->church, $this->staff, AuditEvent::Created, new: ['name' => 'Esi Owusu', 'email' => 'esi@example.com', 'role' => 'finance-secretary'], at: '2026-11-09 09:00:00');
        historyLine($this->church, $this->staff, AuditEvent::StatusChanged, ['is_active' => true], ['is_active' => false], at: '2026-11-09 10:00:00');
        historyLine($this->church, $this->staff, AuditEvent::StatusChanged, ['is_active' => false], ['is_active' => true], at: '2026-11-09 11:00:00');

        expect(feedTexts())->toBe(['Esi Owusu was reactivated.', 'Esi Owusu was deactivated.', 'Esi Owusu was invited as staff.']);
    });

    it('says the church details or settings were changed, without saying to what', function (): void {
        historyLine($this->church, $this->church, AuditEvent::Updated, ['absent_alert' => false], ['absent_alert' => true]);

        expect(feedTexts())->toBe(['The church details or settings were changed.']);
    });

    it('says "A member" for a member whose data was erased, never the marker', function (): void {
        $erased = Member::factory()->create(['church_id' => $this->church->id, 'first_name' => '[deleted]', 'last_name' => '[deleted]']);
        $erased->delete();
        historyLine($this->church, $erased, AuditEvent::Created);
        historyLine($this->church, $erased, AuditEvent::StatusChanged, ['status' => '[erased]'], ['status' => '[erased]'], at: '2026-11-09 12:00:00');

        $text = implode(' ', feedTexts());

        expect($text)->toContain('A member was added as a member.')->toContain('A member\'s status was changed.')->not->toContain('[deleted]');
    });
});

describe('what each role may see', function (): void {
    beforeEach(function (): void {
        historyLine($this->church, $this->member, AuditEvent::Created, at: '2026-11-09 08:00:00');
        historyLine($this->church, $this->gift, AuditEvent::Created, at: '2026-11-09 09:00:00');
        historyLine($this->church, $this->pledge, AuditEvent::Created, at: '2026-11-09 10:00:00');
        historyLine($this->church, $this->staff, AuditEvent::Created, at: '2026-11-09 11:00:00');
        historyLine($this->church, $this->church, AuditEvent::Updated, at: '2026-11-09 12:00:00');
    });

    it('shows the church admin everything', function (): void {
        expect(collect($this->getJson(ACTIVITY)->json('data'))->pluck('kind')->all())
            ->toBe(['settings_changed', 'staff_invited', 'pledge_made', 'gift_recorded', 'member_added']);
    });

    it('shows the pastor the members only, because the pastor may not see individual gifts, staff or settings', function (): void {
        Sanctum::actingAs(activityStaff($this->church, 'pastor'), ['*']);

        expect(collect($this->getJson(ACTIVITY)->json('data'))->pluck('kind')->all())->toBe(['member_added']);
    });

    it('shows the finance secretary the members, gifts and pledges, but not staff or settings', function (): void {
        Sanctum::actingAs(activityStaff($this->church, 'finance-secretary'), ['*']);

        expect(collect($this->getJson(ACTIVITY)->json('data'))->pluck('kind')->all())->toBe(['pledge_made', 'gift_recorded', 'member_added']);
    });

    it('counts only what the person may see, so the totals do not give away what is hidden', function (): void {
        Sanctum::actingAs(activityStaff($this->church, 'pastor'), ['*']);

        $this->getJson(ACTIVITY)->assertJsonPath('meta.total', 1);
    });
});

describe('privacy', function (): void {
    it('never shows a void reason, a status note, an email, a phone number or a member\'s name beside a gift', function (): void {
        historyLine($this->church, $this->gift, AuditEvent::Voided, new: ['is_voided' => true], reason: 'Secret reason for voiding');
        historyLine($this->church, $this->member, AuditEvent::StatusChanged, ['status' => 'active'], ['status' => 'inactive'], reason: 'Private pastoral note');
        historyLine($this->church, $this->staff, AuditEvent::Created, new: ['email' => 'esi@example.com', 'phone' => '+233244000111']);

        $body = $this->getJson(ACTIVITY)->assertOk()->getContent();

        expect($body)->not->toContain('Secret reason')->not->toContain('Private pastoral note')->not->toContain('esi@example.com')
            ->not->toContain('+233244000111')->not->toContain('old_values')->not->toContain('new_values');
    });

    it('leaves out record views, exports, erasures and ordinary edits', function (): void {
        historyLine($this->church, $this->member, AuditEvent::Viewed, new: ['address' => '[viewed]']);
        historyLine($this->church, $this->member, AuditEvent::Exported);
        historyLine($this->church, $this->member, AuditEvent::Erased);
        historyLine($this->church, $this->member, AuditEvent::Updated, ['occupation' => 'Teacher'], ['occupation' => 'Nurse']);
        historyLine($this->church, $this->staff, AuditEvent::Updated, ['role' => 'pastor'], ['role' => 'finance-secretary']);

        $this->getJson(ACTIVITY)->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
    });

    it('never shows another church\'s history (isolation)', function (): void {
        $outsider = Member::factory()->create(['church_id' => $this->other->id, 'first_name' => 'Outsider', 'last_name' => 'Person']);
        historyLine($this->other, $outsider, AuditEvent::Created);

        $response = $this->getJson(ACTIVITY)->assertOk();

        expect($response->json('meta.total'))->toBe(0)->and($response->getContent())->not->toContain('Outsider');
    });
});

describe('paging', function (): void {
    it('lists the newest first and is paged', function (): void {
        foreach (range(1, 3) as $day) {
            $member = Member::factory()->create(['church_id' => $this->church->id, 'first_name' => "Member{$day}", 'last_name' => 'Test']);
            historyLine($this->church, $member, AuditEvent::Created, at: "2026-11-0{$day} 10:00:00");
        }

        $page = $this->getJson(ACTIVITY.'?per_page=2')->assertOk();

        expect(collect($page->json('data'))->pluck('text')->all())->toBe(['Member3 Test was added as a member.', 'Member2 Test was added as a member.'])
            ->and($page->json('meta.total'))->toBe(3);
    });

    it('refuses a page size outside the allowed range', function (): void {
        $this->getJson(ACTIVITY.'?per_page=0')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->getJson(ACTIVITY.'?per_page='.(config('limits.per_page_max') + 1))->assertUnprocessable()->assertJsonValidationErrors('per_page');
    });
});
