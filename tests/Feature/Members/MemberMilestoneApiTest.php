<?php

declare(strict_types=1);

use App\Enums\MemberStatus;
use App\Enums\MilestoneType;
use App\Models\Church;
use App\Models\Member;
use App\Models\MemberMilestone;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

function milestoneStaff(Church $church, string $role): User
{
    $user = User::factory()->for($church)->create();
    $user->assignRole($role);

    return $user;
}

function milestoneAs(User $user): mixed
{
    app('auth')->forgetGuards();

    return test()->withToken($user->createToken('test')->plainTextToken);
}

function milestoneUrl(Member $member): string
{
    return 'http://grace.localhost/api/v1/members/'.$member->id.'/milestones';
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace']);
    $this->admin = milestoneStaff($this->church, 'church-admin');
    $this->member = Member::factory()->create(['church_id' => $this->church->id]);
    $this->other = Church::factory()->create(['slug' => 'other']);
});

it('rejects the endpoint without a token', function (): void {
    $this->postJson(milestoneUrl($this->member), [])->assertUnauthorized();
});

it('records a milestone and who entered it', function (): void {
    $response = milestoneAs($this->admin)->postJson(milestoneUrl($this->member), [
        'type' => 'baptism',
        'occurred_at' => '2026-10-19',
        'officiant' => 'Rev. Mensah',
        'notes' => 'Water baptism by immersion',
    ])->assertCreated()
        ->assertJsonPath('data.type', 'baptism')
        ->assertJsonPath('data.occurred_at', '2026-10-19')
        ->assertJsonPath('data.officiant', 'Rev. Mensah');

    $milestone = MemberMilestone::query()->findOrFail($response->json('data.id'));
    expect($milestone->member_id)->toBe($this->member->id)
        ->and($milestone->church_id)->toBe($this->church->id)
        ->and($milestone->created_by)->toBe($this->admin->id)
        ->and($milestone->type)->toBe(MilestoneType::Baptism);
});

it('lets the pastor record milestones', function (): void {
    milestoneAs(milestoneStaff($this->church, 'pastor'))
        ->postJson(milestoneUrl($this->member), ['type' => 'confirmation', 'occurred_at' => '2026-10-19', 'cohort' => 'Class 2026'])
        ->assertCreated()->assertJsonPath('data.cohort', 'Class 2026');
});

it('fails validation', function (array $body, string $field): void {
    milestoneAs($this->admin)->postJson(milestoneUrl($this->member), $body)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'missing type' => [['occurred_at' => '2026-10-19'], 'type'],
    'unknown type' => [['type' => 'wedding', 'occurred_at' => '2026-10-19'], 'type'],
    'missing date' => [['type' => 'baptism'], 'occurred_at'],
    'bad date format' => [['type' => 'baptism', 'occurred_at' => '19/10/2026'], 'occurred_at'],
    'future date' => [['type' => 'baptism', 'occurred_at' => '2999-01-01'], 'occurred_at'],
]);

it('is forbidden for roles that cannot edit members', function (string $role): void {
    milestoneAs(milestoneStaff($this->church, $role))
        ->postJson(milestoneUrl($this->member), ['type' => 'baptism', 'occurred_at' => '2026-10-19'])->assertForbidden();
})->with(['finance-secretary', 'department-head', 'youth-pastor']);

it('keeps a deceased member read-only', function (): void {
    $this->member->update(['status' => MemberStatus::Deceased]);

    milestoneAs($this->admin)->postJson(milestoneUrl($this->member), ['type' => 'baptism', 'occurred_at' => '2026-10-19'])->assertForbidden();
});

it('returns 404 for another church\'s member and records nothing', function (): void {
    $foreign = Member::factory()->create(['church_id' => $this->other->id]);

    milestoneAs($this->admin)->postJson(milestoneUrl($foreign), ['type' => 'baptism', 'occurred_at' => '2026-10-19'])->assertNotFound();

    expect(MemberMilestone::query()->withoutGlobalScopes()->count())->toBe(0);
});

describe('on the member record', function (): void {
    beforeEach(function (): void {
        MemberMilestone::factory()->create(['church_id' => $this->church->id, 'member_id' => $this->member->id, 'type' => MilestoneType::Baptism, 'occurred_at' => '2010-06-05', 'officiant' => 'Rev. Dr. Acheampong']);
        $this->memberUrl = 'http://grace.localhost/api/v1/members/'.$this->member->id;
    });

    it('shows milestones to the admin, the pastor and the youth pastor', function (string $role): void {
        $this->member->update(['educational_level' => 'jhs_1']);

        milestoneAs(milestoneStaff($this->church, $role))->getJson($this->memberUrl)
            ->assertOk()->assertJsonPath('data.milestones.0.type', 'baptism')->assertJsonPath('data.milestones.0.officiant', 'Rev. Dr. Acheampong');
    })->with(['church-admin', 'pastor', 'youth-pastor']);

    it('hides milestones from the finance secretary', function (): void {
        milestoneAs(milestoneStaff($this->church, 'finance-secretary'))->getJson($this->memberUrl)
            ->assertOk()->assertJsonMissingPath('data.milestones');
    });

    it('shows a milestone just entered when the member is fetched again', function (): void {
        milestoneAs($this->admin)->postJson(milestoneUrl($this->member), ['type' => 'confirmation', 'occurred_at' => '2026-10-19'])->assertCreated();

        milestoneAs($this->admin)->getJson($this->memberUrl)->assertJsonCount(2, 'data.milestones');
    });
});
