<?php

declare(strict_types=1);

use App\Models\Church;
use App\Models\Department;
use App\Models\Member;
use App\Models\MemberDepartment;
use App\Models\MessageLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

// What a broadcast would reach and cost, before anything is sent (docs/11 story 5.1, docs/08 Send Broadcast, QA-08 TC-08-08,
// D-041, D-051, QA-10). Nothing is queued, logged or charged here. A text goes to active members who have not turned SMS off and
// have a phone number; one credit is one part of the text.

const BROADCAST_ESTIMATE = 'http://grace.localhost/api/v1/messages/broadcast/estimate';

/** A staff member of the church with one role. */
function broadcastStaff(Church $church, string $role): User
{
    $user = User::factory()->for($church)->create();
    $user->assignRole($role);

    return $user;
}

/** The estimate body, with a short message unless another is given. */
function estimateBody(array $overrides = []): array
{
    return [...['channel' => 'sms', 'message' => 'Service is at 9am this Sunday.'], ...$overrides];
}

beforeEach(function (): void {
    Queue::fake();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace', 'sms_credits' => 100]);
    $this->other = Church::factory()->create(['slug' => 'other']);
    $this->admin = broadcastStaff($this->church, 'church-admin');
    Sanctum::actingAs($this->admin, ['*']);
});

describe('who would be reached', function (): void {
    it('is every active member with a phone who has not turned SMS off', function (): void {
        Member::factory()->count(3)->create(['church_id' => $this->church->id]);

        $this->postJson(BROADCAST_ESTIMATE, estimateBody())->assertOk()->assertJsonPath('recipients', 3);
    });

    it('leaves out the members who must not be texted', function (): void {
        Member::factory()->create(['church_id' => $this->church->id]);
        Member::factory()->create(['church_id' => $this->church->id, 'sms_opted_in' => false]);
        Member::factory()->create(['church_id' => $this->church->id, 'status' => 'deceased']);
        Member::factory()->create(['church_id' => $this->church->id, 'status' => 'inactive']);
        Member::factory()->create(['church_id' => $this->church->id, 'status' => 'prospect']);
        Member::factory()->create(['church_id' => $this->church->id, 'phone' => '']);
        Member::factory()->create(['church_id' => $this->church->id])->delete();

        $this->postJson(BROADCAST_ESTIMATE, estimateBody())->assertOk()->assertJsonPath('recipients', 1);
    });

    it('never counts another church\'s members (isolation)', function (): void {
        Member::factory()->count(2)->create(['church_id' => $this->church->id]);
        Member::factory()->count(9)->create(['church_id' => $this->other->id]);

        $this->postJson(BROADCAST_ESTIMATE, estimateBody())->assertOk()->assertJsonPath('recipients', 2);
    });

    it('narrows to a status other than active, but never to the deceased', function (): void {
        Member::factory()->create(['church_id' => $this->church->id, 'status' => 'prospect']);
        Member::factory()->create(['church_id' => $this->church->id, 'status' => 'active']);
        Member::factory()->create(['church_id' => $this->church->id, 'status' => 'deceased']);

        $this->postJson(BROADCAST_ESTIMATE, estimateBody(['recipient_filter' => ['status' => 'prospect']]))->assertOk()->assertJsonPath('recipients', 1);
        $this->postJson(BROADCAST_ESTIMATE, estimateBody(['recipient_filter' => ['status' => 'deceased']]))->assertOk()->assertJsonPath('recipients', 0);
    });

    it('narrows to a department', function (): void {
        $youth = Department::factory()->create(['church_id' => $this->church->id, 'name' => 'Youth']);
        $in = Member::factory()->count(2)->create(['church_id' => $this->church->id]);
        Member::factory()->create(['church_id' => $this->church->id]);
        foreach ($in as $member) {
            MemberDepartment::factory()->create(['church_id' => $this->church->id, 'member_id' => $member->id, 'department_id' => $youth->id]);
        }

        $this->postJson(BROADCAST_ESTIMATE, estimateBody(['recipient_filter' => ['department_id' => $youth->id]]))->assertOk()->assertJsonPath('recipients', 2);
    });

    it('reaches nobody through another church\'s department', function (): void {
        Member::factory()->create(['church_id' => $this->church->id]);
        $theirs = Department::factory()->create(['church_id' => $this->other->id]);

        $this->postJson(BROADCAST_ESTIMATE, estimateBody(['recipient_filter' => ['department_id' => $theirs->id]]))->assertOk()->assertJsonPath('recipients', 0);
    });
});

describe('what it would cost', function (): void {
    it('is one credit for each part of the text, for each person', function (): void {
        Member::factory()->count(4)->create(['church_id' => $this->church->id]);

        $this->postJson(BROADCAST_ESTIMATE, estimateBody())->assertOk()->assertExactJson([
            'recipients' => 4, 'parts_per_message' => 1, 'credits_needed' => 4, 'credit_balance' => 100, 'enough_credits' => true,
        ]);
    });

    it('counts a long text as several parts, as the gateway does (160, then 153 each)', function (): void {
        Member::factory()->count(3)->create(['church_id' => $this->church->id]);

        $this->postJson(BROADCAST_ESTIMATE, estimateBody(['message' => str_repeat('a', 160)]))->assertOk()->assertJsonPath('parts_per_message', 1);
        $this->postJson(BROADCAST_ESTIMATE, estimateBody(['message' => str_repeat('a', 161)]))->assertOk()->assertJsonPath('parts_per_message', 2)->assertJsonPath('credits_needed', 6);
    });

    it('counts a text with a letter outside the plain alphabet as the dearer kind (70, then 67 each)', function (): void {
        Member::factory()->create(['church_id' => $this->church->id]);

        $this->postJson(BROADCAST_ESTIMATE, estimateBody(['message' => str_repeat('a', 69).'ɔ']))->assertOk()->assertJsonPath('parts_per_message', 1);
        $this->postJson(BROADCAST_ESTIMATE, estimateBody(['message' => str_repeat('a', 70).'ɔ']))->assertOk()->assertJsonPath('parts_per_message', 2);
    });

    it('says plainly whether the church has enough credits', function (): void {
        Member::factory()->count(5)->create(['church_id' => $this->church->id]);
        $this->church->update(['sms_credits' => 4]);

        $this->postJson(BROADCAST_ESTIMATE, estimateBody())->assertOk()->assertJsonPath('credit_balance', 4)->assertJsonPath('credits_needed', 5)->assertJsonPath('enough_credits', false);
        $this->church->update(['sms_credits' => 5]);
        // Each request starts with the church as the database has it now, as a real one would.
        Sanctum::actingAs(User::query()->find($this->admin->id), ['*']);
        $this->postJson(BROADCAST_ESTIMATE, estimateBody())->assertOk()->assertJsonPath('enough_credits', true);
    });

    it('queues, logs and charges nothing', function (): void {
        Member::factory()->count(3)->create(['church_id' => $this->church->id]);

        $this->postJson(BROADCAST_ESTIMATE, estimateBody())->assertOk();

        Queue::assertNothingPushed();
        expect(MessageLog::query()->count())->toBe(0)->and($this->church->fresh()->sms_credits)->toBe(100);
    });
});

describe('the request', function (): void {
    it('asks for a message and the channel', function (): void {
        $this->postJson(BROADCAST_ESTIMATE, [])->assertStatus(422)->assertJsonValidationErrors(['channel', 'message']);
        $this->postJson(BROADCAST_ESTIMATE, estimateBody(['message' => '   ']))->assertStatus(422)->assertJsonValidationErrors('message');
    });

    it('is for text messages only until WhatsApp broadcasts are open', function (): void {
        $this->postJson(BROADCAST_ESTIMATE, estimateBody(['channel' => 'whatsapp']))->assertStatus(422)->assertJsonValidationErrors('channel');
        $this->postJson(BROADCAST_ESTIMATE, estimateBody(['channel' => 'pigeon']))->assertStatus(422)->assertJsonValidationErrors('channel');
    });

    it('refuses a text that is longer than the most parts a broadcast may use', function (): void {
        config(['messages.broadcast_max_parts' => 2]);

        $this->postJson(BROADCAST_ESTIMATE, estimateBody(['message' => str_repeat('a', 306)]))->assertOk()->assertJsonPath('parts_per_message', 2);
        $this->postJson(BROADCAST_ESTIMATE, estimateBody(['message' => str_repeat('a', 307)]))->assertStatus(422)->assertJsonValidationErrors('message');
    });

    it('refuses a filter that is not valid', function (): void {
        $this->postJson(BROADCAST_ESTIMATE, estimateBody(['recipient_filter' => ['status' => 'retired']]))->assertStatus(422)->assertJsonValidationErrors('recipient_filter.status');
        $this->postJson(BROADCAST_ESTIMATE, estimateBody(['recipient_filter' => ['department_id' => 'youth']]))->assertStatus(422)->assertJsonValidationErrors('recipient_filter.department_id');
    });
});

describe('who may', function (): void {
    it('refuses a visitor with no sign-in', function (): void {
        $this->app['auth']->forgetGuards();

        $this->postJson(BROADCAST_ESTIMATE, estimateBody())->assertUnauthorized();
    });

    it('lets the roles with sms.send', function (string $role): void {
        Sanctum::actingAs(broadcastStaff($this->church, $role), ['*']);

        $this->postJson(BROADCAST_ESTIMATE, estimateBody())->assertOk();
    })->with(['church-admin', 'pastor']);

    it('refuses the roles without sms.send', function (string $role): void {
        Sanctum::actingAs(broadcastStaff($this->church, $role), ['*']);

        $this->postJson(BROADCAST_ESTIMATE, estimateBody())->assertForbidden()->assertJsonPath('message', 'This action is unauthorized.');
    })->with(['finance-secretary', 'department-head', 'youth-pastor']);
});
