<?php

declare(strict_types=1);

use App\Enums\MessageStatus;
use App\Jobs\SendAbsenceAlertsJob;
use App\Jobs\SendSmsJob;
use App\Models\AttendanceRecord;
use App\Models\Church;
use App\Models\Member;
use App\Models\MessageLog;
use App\Models\Service;
use App\Services\SmsSegmenter;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

// The automatic text to members who have missed several services in a row (docs/02, docs/11 story 3.13, D-044, D-051, D-067). It is
// off until the church switches it on, costs the church's credits, goes once for each stretch of absence, and is worked out
// each day from the same list as `GET /attendance/absent`. Today is Monday 9 November 2026; the Sundays before it are the 8th,
// 1st and 25 October.

/** A Sunday service on this date with these members checked in. */
function alertService(Church $church, string $date, array $present = []): Service
{
    $service = Service::factory()->create(['church_id' => $church->id, 'service_date' => $date, 'type' => 'sunday']);
    foreach ($present as $member) {
        AttendanceRecord::factory()->create(['church_id' => $church->id, 'service_id' => $service->id, 'member_id' => $member->id, 'checked_in_at' => $date.' 10:00:00']);
    }

    return $service;
}

/** Runs the church's alert job now, as a worker would. */
function runAlerts(Church $church): void
{
    app()->call([new SendAbsenceAlertsJob($church->id), 'handle']);
}

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-11-09 09:00:00');
    Queue::fake();
    $this->church = Church::factory()->create(['slug' => 'grace', 'name' => 'Grace Assembly', 'sms_credits' => 100, 'settings' => ['absent_alert' => true]]);
    $this->other = Church::factory()->create(['slug' => 'other', 'sms_credits' => 100, 'settings' => ['absent_alert' => true]]);
    // A member who comes every week, so each Sunday counts as held, and one who has not come at all.
    $this->regular = Member::factory()->create(['church_id' => $this->church->id, 'first_name' => 'Regular', 'created_at' => '2026-01-01']);
    $this->absent = Member::factory()->create(['church_id' => $this->church->id, 'first_name' => 'Kofi', 'preferred_name' => null, 'phone' => '+233244111222', 'created_at' => '2026-01-01']);
    foreach (['2026-10-25', '2026-11-01', '2026-11-08'] as $date) {
        alertService($this->church, $date, [$this->regular]);
    }
});

afterEach(fn () => CarbonImmutable::setTestNow());

describe('who is texted, and how', function (): void {
    it('texts a member who missed the last three services, paid for by the church, with a log line', function (): void {
        runAlerts($this->church);

        $log = MessageLog::query()->where('message_type', 'attendance_alert')->sole();
        $text = (string) config('attendance.absence_alert_message');
        expect($log->recipient_id)->toBe($this->absent->id)->and($log->recipient_phone)->toBe('+233244111222')->and($log->status)->toBe(MessageStatus::Queued)
            ->and($log->content)->toContain('Kofi')->toContain('Grace Assembly')->and($log->content)->not->toContain(':name')->not->toContain(':church');
        Queue::assertPushedOn('notifications', SendSmsJob::class);
        Queue::assertPushed(SendSmsJob::class, fn (SendSmsJob $job): bool => $job->sms->messageLogId === $log->id && $job->sms->messageType === 'attendance_alert' && $job->sms->churchId === $this->church->id
            && $job->sms->creditCost === app(SmsSegmenter::class)->parts($log->content) && $job->sms->phone === '+233244111222');
        expect($text)->toContain(':name');
    });

    it('calls the member by the name they like to be called', function (): void {
        $this->absent->update(['preferred_name' => 'Yaw']);

        runAlerts($this->church);

        expect(MessageLog::query()->where('message_type', 'attendance_alert')->sole()->content)->toContain('Yaw')->not->toContain('Kofi');
    });

    it('is written in the plain alphabet, so it costs the cheapest parts', function (): void {
        runAlerts($this->church);

        expect(Queue::pushed(SendSmsJob::class)->sole()->sms->creditCost)->toBeLessThanOrEqual(2);
    });

    it('leaves out a member who came to any of the last services', function (): void {
        $came = Member::factory()->create(['church_id' => $this->church->id, 'created_at' => '2026-01-01']);
        AttendanceRecord::factory()->create(['church_id' => $this->church->id, 'service_id' => Service::query()->where('service_date', '2026-11-01')->first()->id, 'member_id' => $came->id]);

        runAlerts($this->church);

        expect(MessageLog::query()->where('message_type', 'attendance_alert')->pluck('recipient_id')->all())->toBe([$this->absent->id]);
    });

    it('leaves out members who must not be texted, or are not active, or joined too late', function (): void {
        Member::factory()->create(['church_id' => $this->church->id, 'sms_opted_in' => false, 'created_at' => '2026-01-01']);
        Member::factory()->create(['church_id' => $this->church->id, 'phone' => '', 'created_at' => '2026-01-01']);
        Member::factory()->create(['church_id' => $this->church->id, 'status' => 'inactive', 'created_at' => '2026-01-01']);
        Member::factory()->create(['church_id' => $this->church->id, 'status' => 'prospect', 'created_at' => '2026-01-01']);
        Member::factory()->create(['church_id' => $this->church->id, 'created_at' => '2026-10-30']);

        runAlerts($this->church);

        expect(MessageLog::query()->where('message_type', 'attendance_alert')->pluck('recipient_id')->all())->toBe([$this->absent->id]);
    });

    it('waits until there are enough services to say anyone missed that many in a row', function (): void {
        Service::query()->where('service_date', '2026-10-25')->delete();

        runAlerts($this->church);

        Queue::assertNothingPushed();
    });

    it('uses the church\'s own number of services in a row', function (): void {
        $this->church->update(['settings' => ['absent_alert' => true, 'absence_threshold' => 2]]);
        $oneMiss = Member::factory()->create(['church_id' => $this->church->id, 'created_at' => '2026-01-01']);
        AttendanceRecord::factory()->create(['church_id' => $this->church->id, 'service_id' => Service::query()->where('service_date', '2026-10-25')->first()->id, 'member_id' => $oneMiss->id]);

        runAlerts($this->church->fresh());

        expect(MessageLog::query()->where('message_type', 'attendance_alert')->pluck('recipient_id')->sort()->values()->all())->toBe(collect([$this->absent->id, $oneMiss->id])->sort()->values()->all());
    });

    it('never texts another church\'s members (isolation)', function (): void {
        Member::factory()->count(3)->create(['church_id' => $this->other->id, 'created_at' => '2026-01-01']);

        runAlerts($this->church);

        expect(MessageLog::query()->withoutGlobalScopes()->where('church_id', $this->other->id)->count())->toBe(0);
    });
});

describe('once for each stretch of absence', function (): void {
    it('does not text the same member again on the next day', function (): void {
        runAlerts($this->church);
        $this->travel(1)->day();
        runAlerts($this->church);

        expect(MessageLog::query()->where('message_type', 'attendance_alert')->count())->toBe(1);
        Queue::assertPushed(SendSmsJob::class, 1);
    });

    it('texts again only after the member has been back and then missed the same number again', function (): void {
        runAlerts($this->church);

        // They come on 15 November, then miss the three after it.
        $this->travel(7)->days();
        $back = alertService($this->church, '2026-11-15', [$this->regular, $this->absent]);
        foreach (['2026-11-22', '2026-11-29', '2026-12-06'] as $date) {
            alertService($this->church, $date, [$this->regular]);
        }
        $this->travel(21)->days();
        CarbonImmutable::setTestNow('2026-12-07 09:00:00');
        runAlerts($this->church);

        expect(MessageLog::query()->where('message_type', 'attendance_alert')->where('recipient_id', $this->absent->id)->count())->toBe(2)->and($back->id)->not->toBeNull();
    });

    it('tries a member again if the earlier text failed for good', function (): void {
        runAlerts($this->church);
        MessageLog::query()->where('message_type', 'attendance_alert')->update(['status' => MessageStatus::Failed]);

        runAlerts($this->church);

        expect(MessageLog::query()->where('message_type', 'attendance_alert')->count())->toBe(2);
    });
});

describe('the credits', function (): void {
    it('stops when the church runs out, texting as many as it can pay for, and tries the rest the next day', function (): void {
        Member::factory()->count(2)->create(['church_id' => $this->church->id, 'created_at' => '2026-01-01']);
        $parts = app(SmsSegmenter::class)->parts(str_replace([':name', ':church'], ['Kofi', 'Grace Assembly'], (string) config('attendance.absence_alert_message')));
        $this->church->update(['sms_credits' => $parts * 2]);
        Log::spy();

        runAlerts($this->church->fresh());

        expect(MessageLog::query()->where('message_type', 'attendance_alert')->count())->toBe(2);
        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => $message === 'Absence alerts stopped: insufficient credits' && $context === ['church_id' => $this->church->id]);

        $this->church->update(['sms_credits' => 100]);
        runAlerts($this->church->fresh());
        expect(MessageLog::query()->where('message_type', 'attendance_alert')->count())->toBe(3);
    });

    it('takes no credits itself: the SMS job takes them', function (): void {
        runAlerts($this->church);

        expect($this->church->fresh()->sms_credits)->toBe(100);
    });
});

describe('the switch', function (): void {
    it('is off for a church that has not chosen', function (): void {
        $this->church->update(['settings' => null]);

        runAlerts($this->church->fresh());

        Queue::assertNothingPushed();
        expect(MessageLog::query()->count())->toBe(0);
    });

    it('is off when the church turned it off', function (): void {
        $this->church->update(['settings' => ['absent_alert' => false]]);

        runAlerts($this->church->fresh());

        Queue::assertNothingPushed();
    });
});

describe('the daily command', function (): void {
    it('queues the job only for churches that switched it on and are still running', function (): void {
        $off = Church::factory()->create(['slug' => 'off', 'settings' => ['absent_alert' => false]]);
        $none = Church::factory()->create(['slug' => 'none', 'settings' => null]);
        $purged = Church::factory()->create(['slug' => 'purged', 'settings' => ['absent_alert' => true], 'purged_at' => now()->subDay()]);
        $suspended = Church::factory()->create(['slug' => 'suspended', 'settings' => ['absent_alert' => true], 'is_active' => false]);

        $this->artisan('attendance:alert-absent')->assertSuccessful();

        Queue::assertPushedOn('notifications', SendAbsenceAlertsJob::class);
        $queued = Queue::pushed(SendAbsenceAlertsJob::class)->map(fn (SendAbsenceAlertsJob $job): string => $job->churchId)->sort()->values()->all();
        expect($queued)->toBe(collect([$this->church->id, $this->other->id])->sort()->values()->all())
            ->and($queued)->not->toContain($off->id)->not->toContain($none->id)->not->toContain($purged->id)->not->toContain($suspended->id);
    });

    it('is run every day', function (): void {
        $scheduled = collect(app(Schedule::class)->events())->contains(fn ($event): bool => str_contains((string) $event->command, 'attendance:alert-absent'));

        expect($scheduled)->toBeTrue();
    });
});

describe('the job', function (): void {
    it('marks nothing and sends nothing for a church that no longer exists', function (): void {
        app()->call([new SendAbsenceAlertsJob('01HZZZZZZZZZZZZZZZZZZZZZZZ'), 'handle']);

        Queue::assertNothingPushed();
    });
});
