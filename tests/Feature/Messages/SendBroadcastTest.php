<?php

declare(strict_types=1);

use App\Enums\BroadcastStatus;
use App\Enums\MessageStatus;
use App\Jobs\SendBroadcastJob;
use App\Jobs\SendSmsJob;
use App\Models\Broadcast;
use App\Models\Church;
use App\Models\Department;
use App\Models\Member;
use App\Models\MemberDepartment;
use App\Models\MessageLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

// Sending a broadcast (docs/11 story 5.1, docs/08 Send Broadcast, QA-08 TC-08-07, 09, 10, QA-10, D-041, D-051, D-065). The request
// answers 202 at once and records the broadcast; a worker turns it into one text for each member, which the SMS job pays for
// (credits, D-051) and sends. Today's estimate rules decide who is reached.

const SEND_BROADCAST = 'http://grace.localhost/api/v1/messages/broadcast';

/** A staff member of the church with one role. */
function sendStaff(Church $church, string $role): User
{
    $user = User::factory()->for($church)->create();
    $user->assignRole($role);

    return $user;
}

/** Sends a broadcast with a fresh Idempotency-Key unless one is given. */
function sendBroadcast(array $body = [], ?string $key = null): TestResponse
{
    $body = [...['channel' => 'sms', 'message' => 'Service is at 9am this Sunday.'], ...$body];

    return test()->postJson(SEND_BROADCAST, $body, ['Idempotency-Key' => $key ?? 'broadcast-'.Str::random(20)]);
}

/** Runs the worker's job for a broadcast now. */
function fanOut(string $broadcastId): void
{
    app()->call([new SendBroadcastJob($broadcastId), 'handle']);
}

beforeEach(function (): void {
    Queue::fake();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace', 'sms_credits' => 100]);
    $this->other = Church::factory()->create(['slug' => 'other', 'sms_credits' => 100]);
    $this->admin = sendStaff($this->church, 'church-admin');
    Sanctum::actingAs($this->admin, ['*']);
});

describe('asking to send', function (): void {
    it('answers 202 at once, records the broadcast and leaves the work to a worker', function (): void {
        Member::factory()->count(3)->create(['church_id' => $this->church->id]);

        $response = sendBroadcast(['recipient_filter' => ['status' => 'active']])->assertStatus(202);

        $broadcast = Broadcast::query()->sole();
        expect($response->json())->toBe(['queued' => true, 'broadcast_id' => $broadcast->id, 'estimated_recipients' => 3, 'estimated_credit_cost' => 3, 'current_credit_balance' => 100])
            ->and($broadcast->sent_by)->toBe($this->admin->id)->and($broadcast->status)->toBe(BroadcastStatus::Queued)->and($broadcast->message)->toBe('Service is at 9am this Sunday.')
            ->and($broadcast->parts_per_message)->toBe(1)->and($broadcast->filters)->toBe(['status' => 'active']);
        Queue::assertPushedOn('notifications', SendBroadcastJob::class, fn (SendBroadcastJob $job): bool => $job->broadcastId === $broadcast->id);
        Queue::assertNotPushed(SendSmsJob::class);
        expect(MessageLog::query()->count())->toBe(0)->and($this->church->fresh()->sms_credits)->toBe(100);
    });

    it('needs an Idempotency-Key, and a repeated key sends once', function (): void {
        Member::factory()->create(['church_id' => $this->church->id]);

        $this->postJson(SEND_BROADCAST, ['channel' => 'sms', 'message' => 'Hello'])->assertStatus(400);

        sendBroadcast([], 'same-broadcast-key')->assertStatus(202);
        sendBroadcast([], 'same-broadcast-key')->assertStatus(202)->assertHeader('Idempotent-Replayed', 'true');
        expect(Broadcast::query()->count())->toBe(1);
        Queue::assertPushed(SendBroadcastJob::class, 1);
    });

    it('is refused when the church cannot pay, saying what is needed, and records nothing', function (): void {
        Member::factory()->count(60)->create(['church_id' => $this->church->id]);
        $this->church->update(['sms_credits' => 30]);

        sendBroadcast()->assertStatus(422)->assertJsonPath('message', 'Insufficient SMS credits. You need 60 credits, you have 30. Purchase more credits to proceed.');

        expect(Broadcast::query()->count())->toBe(0);
        Queue::assertNothingPushed();
    });

    it('counts every part of a long text against the credits', function (): void {
        Member::factory()->count(3)->create(['church_id' => $this->church->id]);
        $this->church->update(['sms_credits' => 5]);

        sendBroadcast(['message' => str_repeat('a', 200)])->assertStatus(422)->assertJsonPath('message', 'Insufficient SMS credits. You need 6 credits, you have 5. Purchase more credits to proceed.');
        $this->church->update(['sms_credits' => 6]);
        Sanctum::actingAs(User::query()->find($this->admin->id), ['*']);
        sendBroadcast(['message' => str_repeat('a', 200)])->assertStatus(202)->assertJsonPath('estimated_credit_cost', 6);
    });

    it('is refused when nobody would be reached', function (): void {
        sendBroadcast()->assertStatus(422)->assertJsonPath('message', 'No members match: there is nobody to send this to.');

        expect(Broadcast::query()->count())->toBe(0);
    });

    it('follows the same rules as the estimate for the text and the filter', function (): void {
        Member::factory()->create(['church_id' => $this->church->id]);

        sendBroadcast(['channel' => 'whatsapp'])->assertStatus(422)->assertJsonValidationErrors('channel');
        sendBroadcast(['message' => ''])->assertStatus(422)->assertJsonValidationErrors('message');
        sendBroadcast(['message' => str_repeat('a', 700)])->assertStatus(422)->assertJsonValidationErrors('message');
        sendBroadcast(['recipient_filter' => ['status' => 'retired']])->assertStatus(422)->assertJsonValidationErrors('recipient_filter.status');
        expect(Broadcast::query()->count())->toBe(0);
    });

    it('is limited to a few broadcasts an hour for each church', function (): void {
        config(['limits.broadcasts_per_hour' => 2]);
        Member::factory()->create(['church_id' => $this->church->id]);

        sendBroadcast()->assertStatus(202);
        sendBroadcast()->assertStatus(202);
        sendBroadcast()->assertStatus(429)->assertJsonPath('message', 'You can send at most 2 broadcasts per hour.')->assertHeader('Retry-After');
        expect(Broadcast::query()->count())->toBe(2);
    });
});

describe('the limit', function (): void {
    it('is for each church, not shared between churches', function (): void {
        config(['limits.broadcasts_per_hour' => 1]);
        Member::factory()->create(['church_id' => $this->church->id]);
        Member::factory()->create(['church_id' => $this->other->id]);

        sendBroadcast()->assertStatus(202);
        sendBroadcast()->assertStatus(429);

        Sanctum::actingAs(sendStaff($this->other, 'church-admin'), ['*']);
        $this->postJson('http://other.localhost/api/v1/messages/broadcast', ['channel' => 'sms', 'message' => 'Hello from the other church'], ['Idempotency-Key' => 'other-church-key-1'])->assertStatus(202);
    });
});

describe('who may send', function (): void {
    it('refuses a visitor with no sign-in', function (): void {
        $this->app['auth']->forgetGuards();

        sendBroadcast()->assertUnauthorized();
    });

    it('lets the roles with sms.send', function (string $role): void {
        Member::factory()->create(['church_id' => $this->church->id]);
        Sanctum::actingAs(sendStaff($this->church, $role), ['*']);

        sendBroadcast()->assertStatus(202);
    })->with(['church-admin', 'pastor']);

    it('refuses the roles without sms.send', function (string $role): void {
        Member::factory()->create(['church_id' => $this->church->id]);
        Sanctum::actingAs(sendStaff($this->church, $role), ['*']);

        sendBroadcast()->assertForbidden()->assertJsonPath('message', 'This action is unauthorized.');
        expect(Broadcast::query()->count())->toBe(0);
    })->with(['finance-secretary', 'department-head', 'youth-pastor']);
});

describe('the worker', function (): void {
    it('makes one logged text for each member, queued and charged by the SMS job, not here', function (): void {
        $kofi = Member::factory()->create(['church_id' => $this->church->id, 'phone' => '+233244000111']);
        Member::factory()->create(['church_id' => $this->church->id, 'phone' => '+233244000222']);
        $broadcast = Broadcast::factory()->create(['church_id' => $this->church->id, 'sent_by' => $this->admin->id, 'message' => str_repeat('a', 200), 'parts_per_message' => 2]);

        fanOut($broadcast->id);

        $logs = MessageLog::query()->where('broadcast_id', $broadcast->id)->get();
        expect($logs)->toHaveCount(2)->and($logs->pluck('message_type')->unique()->all())->toBe(['broadcast'])->and($logs->pluck('status')->unique()->all())->toBe([MessageStatus::Queued])
            ->and($logs->firstWhere('recipient_id', $kofi->id)->recipient_phone)->toBe('+233244000111')->and($logs->first()->content)->toBe(str_repeat('a', 200));
        Queue::assertPushed(SendSmsJob::class, 2);
        Queue::assertPushedOn('notifications', SendSmsJob::class);
        Queue::assertPushed(SendSmsJob::class, fn (SendSmsJob $job): bool => $job->sms->creditCost === 2 && $job->sms->churchId === $this->church->id && $job->sms->messageType === 'broadcast' && $job->sms->messageLogId !== null);
        expect($this->church->fresh()->sms_credits)->toBe(100);
        expect($broadcast->refresh()->status)->toBe(BroadcastStatus::Dispatched)->and($broadcast->recipient_count)->toBe(2);
    });

    it('reaches only who the filters and the consent rules allow, as things are when it runs', function (): void {
        $youth = Department::factory()->create(['church_id' => $this->church->id]);
        $in = Member::factory()->create(['church_id' => $this->church->id]);
        $leftOut = Member::factory()->create(['church_id' => $this->church->id]);
        $optedOutLater = Member::factory()->create(['church_id' => $this->church->id]);
        foreach ([$in, $optedOutLater] as $member) {
            MemberDepartment::factory()->create(['church_id' => $this->church->id, 'member_id' => $member->id, 'department_id' => $youth->id]);
        }
        $broadcast = Broadcast::factory()->create(['church_id' => $this->church->id, 'sent_by' => $this->admin->id, 'filters' => ['department_id' => $youth->id]]);
        $optedOutLater->update(['sms_opted_in' => false]);

        fanOut($broadcast->id);

        expect(MessageLog::query()->where('broadcast_id', $broadcast->id)->pluck('recipient_id')->all())->toBe([$in->id])->and($leftOut->id)->not->toBeNull();
    });

    it('never texts another church\'s members (isolation)', function (): void {
        Member::factory()->create(['church_id' => $this->church->id]);
        Member::factory()->count(5)->create(['church_id' => $this->other->id]);
        $broadcast = Broadcast::factory()->create(['church_id' => $this->church->id, 'sent_by' => $this->admin->id]);

        fanOut($broadcast->id);

        expect(MessageLog::query()->withoutGlobalScopes()->where('broadcast_id', $broadcast->id)->count())->toBe(1);
    });

    it('does nothing the second time, so nobody is texted twice', function (): void {
        Member::factory()->count(3)->create(['church_id' => $this->church->id]);
        $broadcast = Broadcast::factory()->create(['church_id' => $this->church->id, 'sent_by' => $this->admin->id]);

        fanOut($broadcast->id);
        fanOut($broadcast->id);

        expect(MessageLog::query()->where('broadcast_id', $broadcast->id)->count())->toBe(3);
        Queue::assertPushed(SendSmsJob::class, 3);
    });

    it('carries on after a crash without texting the same member again', function (): void {
        $done = Member::factory()->create(['church_id' => $this->church->id]);
        $todo = Member::factory()->create(['church_id' => $this->church->id]);
        $broadcast = Broadcast::factory()->create(['church_id' => $this->church->id, 'sent_by' => $this->admin->id, 'status' => BroadcastStatus::Sending]);
        MessageLog::factory()->create(['church_id' => $this->church->id, 'broadcast_id' => $broadcast->id, 'recipient_id' => $done->id, 'message_type' => 'broadcast']);

        fanOut($broadcast->id);

        expect(MessageLog::query()->where('broadcast_id', $broadcast->id)->pluck('recipient_id')->sort()->values()->all())->toBe(collect([$done->id, $todo->id])->sort()->values()->all())
            ->and($broadcast->refresh()->recipient_count)->toBe(2);
        Queue::assertPushed(SendSmsJob::class, 1);
    });

    it('is built in pieces, so a large church does not fill the memory', function (): void {
        config(['messages.broadcast_chunk_size' => 2]);
        Member::factory()->count(7)->create(['church_id' => $this->church->id]);
        $broadcast = Broadcast::factory()->create(['church_id' => $this->church->id, 'sent_by' => $this->admin->id]);

        fanOut($broadcast->id);

        expect(MessageLog::query()->where('broadcast_id', $broadcast->id)->count())->toBe(7);
        Queue::assertPushed(SendSmsJob::class, 7);
    });

    it('quietly does nothing for a broadcast that no longer exists or has already failed', function (): void {
        fanOut('01HZZZZZZZZZZZZZZZZZZZZZZZ');
        $failed = Broadcast::factory()->create(['church_id' => $this->church->id, 'status' => BroadcastStatus::Failed]);
        Member::factory()->create(['church_id' => $this->church->id]);

        fanOut($failed->id);

        Queue::assertNothingPushed();
    });

    it('marks the broadcast failed when the job gives up', function (): void {
        $broadcast = Broadcast::factory()->create(['church_id' => $this->church->id]);

        (new SendBroadcastJob($broadcast->id))->failed(new RuntimeException('the queue is down'));

        expect($broadcast->refresh()->status)->toBe(BroadcastStatus::Failed);
    });

    it('shows its texts in the message log, as broadcasts', function (): void {
        Member::factory()->count(2)->create(['church_id' => $this->church->id]);
        $broadcast = Broadcast::factory()->create(['church_id' => $this->church->id, 'sent_by' => $this->admin->id]);
        fanOut($broadcast->id);

        $rows = $this->getJson('http://grace.localhost/api/v1/messages?message_type=broadcast')->assertOk()->json('data');

        expect($rows)->toHaveCount(2)->and($rows[0]['message_type'])->toBe('broadcast')->and($rows[0]['status'])->toBe('queued');
    });
});
