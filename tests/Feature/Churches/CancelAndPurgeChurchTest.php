<?php

declare(strict_types=1);

use App\Actions\Churches\PurgeChurchAction;
use App\DTOs\Churches\PurgeChurchDTO;
use App\Enums\MemberStatus;
use App\Jobs\PurgeChurchJob;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Church;
use App\Models\Contribution;
use App\Models\Family;
use App\Models\Member;
use App\Models\MessageLog;
use App\Models\Service;
use App\Models\User;
use App\Models\VisitorRecord;
use App\Repositories\ChurchPurgeRepositoryInterface;
use App\Repositories\EloquentChurchPurgeRepository;
use App\Services\ChurchPurgeService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

// docs/05: a church that cancels keeps working for 90 days so it can take its data, then its personal data is purged.
// Money records stay for 7 years with no personal details in them.

/** A church with a little of everything that holds personal details, so a purge has something to clean. */
function churchWithData(string $slug): Church
{
    $church = Church::factory()->create([
        'slug' => $slug, 'address' => '1 Ring Road', 'phone' => '+233302000000', 'email' => 'office@example.com',
        'payout_account_number' => '0244000000', 'payout_account_name' => 'Grace Assembly',
    ]);
    $admin = User::factory()->create(['church_id' => $church->id, 'name' => 'Kofi Mensah', 'phone' => '+233244111222', 'email' => 'kofi@example.com']);
    $admin->createToken('staff');
    $member = Member::factory()->create([
        'church_id' => $church->id, 'created_by' => $admin->id, 'first_name' => 'Abena', 'last_name' => 'Asante',
        'phone' => '+233200987654', 'address' => '12 Ring Road', 'photo_url' => "churches/{$church->id}/members/x/photo.jpg",
    ]);
    $family = Family::factory()->create(['church_id' => $church->id, 'name' => 'Asante Family', 'notes' => 'Lives with her aunt Ama']);
    $service = Service::factory()->create(['church_id' => $church->id, 'created_by' => $admin->id]);
    VisitorRecord::factory()->create(['church_id' => $church->id, 'name' => 'Yaw Boateng', 'phone' => '+233244999000', 'notes' => 'Came with Kofi']);
    AttendanceRecord::factory()->create(['church_id' => $church->id, 'service_id' => $service->id, 'member_id' => null, 'visitor_name' => 'Yaw Boateng']);
    Contribution::factory()->create(['church_id' => $church->id, 'member_id' => $member->id, 'amount' => '200.00', 'notes' => 'Paid for Ama', 'recorded_by' => $admin->id]);
    MessageLog::factory()->create(['church_id' => $church->id, 'recipient_id' => $member->id, 'recipient_phone' => '+233200987654', 'content' => 'Hello Abena']);
    AuditLog::factory()->create(['church_id' => $church->id, 'auditable_type' => 'member', 'auditable_id' => $member->id, 'old_values' => ['occupation' => 'Nurse'], 'new_values' => ['occupation' => 'Teacher'], 'reason' => 'Because Abena asked']);
    $family->update(['head_member_id' => $member->id]);

    return $church->fresh();
}

describe('cancelling', function (): void {
    it('starts the grace period and says when the data will be purged', function (): void {
        $church = Church::factory()->create(['slug' => 'grace']);

        $this->artisan('church:cancel', ['slug' => 'grace'])
            ->expectsOutputToContain('purged on '.now()->addDays(90)->toDateString())
            ->assertSuccessful();

        expect($church->fresh()->cancelled_at)->not->toBeNull()->and($church->fresh()->is_active)->toBeTrue();
    });

    it('keeps the first date when cancelled twice', function (): void {
        $church = Church::factory()->create(['slug' => 'grace', 'cancelled_at' => now()->subDays(10)]);
        $first = $church->cancelled_at;

        $this->artisan('church:cancel', ['slug' => 'grace'])->assertSuccessful();

        expect($church->fresh()->cancelled_at->equalTo($first))->toBeTrue();
    });

    it('logs the cancellation with ids only', function (): void {
        Log::spy();
        $church = Church::factory()->create(['slug' => 'grace']);

        $this->artisan('church:cancel', ['slug' => 'grace'])->assertSuccessful();

        Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => $message === 'Church cancelled'
            && $context['church_id'] === $church->id && array_keys($context) === ['church_id', 'purge_after']);
    });

    it('fails for an unknown web address', function (): void {
        $this->artisan('church:cancel', ['slug' => 'nobody'])->expectsOutputToContain('No church has that web address')->assertFailed();
    });

    it('can be taken back during the grace period', function (): void {
        $church = Church::factory()->create(['slug' => 'grace', 'cancelled_at' => now()->subDays(30)]);

        $this->artisan('church:reinstate', ['slug' => 'grace'])->assertSuccessful();

        expect($church->fresh()->cancelled_at)->toBeNull();
    });

    it('cannot be taken back after the purge', function (): void {
        $church = Church::factory()->create(['slug' => 'grace', 'cancelled_at' => now()->subDays(100), 'purged_at' => now()]);

        $this->artisan('church:reinstate', ['slug' => 'grace'])->expectsOutputToContain('already been purged')->assertFailed();

        expect($church->fresh()->cancelled_at)->not->toBeNull();
    });
});

describe('finding churches to purge', function (): void {
    it('queues a purge only for churches whose grace period is over and which are not purged', function (): void {
        Queue::fake();
        $due = Church::factory()->create(['cancelled_at' => now()->subDays(91)]);
        Church::factory()->create(['cancelled_at' => now()->subDays(89)]);
        Church::factory()->create(['cancelled_at' => now()->subDays(200), 'purged_at' => now()->subDays(100)]);
        Church::factory()->create();

        $this->artisan('churches:purge-cancelled')->expectsOutputToContain('Queued the purge of 1 church')->assertSuccessful();

        Queue::assertPushed(PurgeChurchJob::class, 1);
        Queue::assertPushed(PurgeChurchJob::class, fn (PurgeChurchJob $job): bool => $job->churchId === $due->id && $job->queue === 'scheduled');
    });

    it('runs every day at 03:30 UTC', function (): void {
        $event = collect(app(Schedule::class)->events())->first(fn ($event): bool => str_contains($event->command, 'churches:purge-cancelled'));

        expect($event)->not->toBeNull()->and($event->expression)->toBe('30 3 * * *')->and($event->timezone)->toBe('UTC');
    });
});

describe('purging', function (): void {
    beforeEach(function (): void {
        Storage::fake();
        $this->church = churchWithData('grace');
        $this->other = churchWithData('other');
        Storage::put("churches/{$this->church->id}/members/x/photo.jpg", 'bytes');
        Storage::put("churches/{$this->other->id}/members/x/photo.jpg", 'bytes');
        // A file no member points to, such as an old export, must go with the church too.
        Storage::put("churches/{$this->church->id}/exports/members.csv", 'bytes');
        $this->church->update(['cancelled_at' => now()->subDays(91)]);
        $this->purge = fn () => app(PurgeChurchAction::class)->execute(new PurgeChurchDTO($this->church->fresh()));
    });

    it('erases every member', function (): void {
        ($this->purge)();

        $member = Member::query()->withoutGlobalScopes()->withTrashed()->where('church_id', $this->church->id)->sole();
        expect($member->trashed())->toBeTrue()
            ->and($member->first_name)->toBe('[deleted]')
            ->and($member->phone)->toBe('[deleted]')
            ->and($member->address)->toBeNull()
            ->and($member->status)->toBe(MemberStatus::Archived);
    });

    it('removes staff details and ends their sessions', function (): void {
        ($this->purge)();

        $staff = User::query()->withoutGlobalScopes()->withTrashed()->where('church_id', $this->church->id)->sole();
        expect($staff->name)->toBe('[deleted]')->and($staff->phone)->toBe('[deleted]')->and($staff->email)->toBeNull()
            ->and($staff->is_active)->toBeFalse()->and($staff->trashed())->toBeTrue()->and($staff->tokens()->count())->toBe(0);
    });

    it('cleans families, visitors, attendance names and contribution notes but keeps the money', function (): void {
        ($this->purge)();

        $family = Family::query()->withoutGlobalScopes()->withTrashed()->where('church_id', $this->church->id)->sole();
        $visitor = VisitorRecord::query()->withoutGlobalScopes()->withTrashed()->where('church_id', $this->church->id)->sole();
        $attendance = AttendanceRecord::query()->withoutGlobalScopes()->where('church_id', $this->church->id)->sole();
        $contribution = Contribution::query()->withoutGlobalScopes()->where('church_id', $this->church->id)->sole();
        expect($family->name)->toBe('[deleted]')->and($family->notes)->toBeNull()
            ->and($visitor->name)->toBe('[deleted]')->and($visitor->phone)->toBe('[deleted]')->and($visitor->notes)->toBeNull()
            ->and($attendance->visitor_name)->toBe('[deleted]')
            ->and($contribution->notes)->toBeNull()->and($contribution->amount)->toBe('200.00');
    });

    it('deletes the messages and masks the history', function (): void {
        ($this->purge)();

        expect(MessageLog::query()->withoutGlobalScopes()->where('church_id', $this->church->id)->count())->toBe(0);
        $lines = AuditLog::query()->withoutGlobalScopes()->where('church_id', $this->church->id)->get();
        expect($lines->isNotEmpty())->toBeTrue()
            ->and(json_encode($lines->map(fn ($l) => [$l->old_values, $l->new_values, $l->reason])))->not->toContain('Teacher')->not->toContain('Nurse')->not->toContain('Abena');
    });

    it('removes the church\'s contact and payout details, deletes it and frees its web address', function (): void {
        ($this->purge)();

        $church = Church::query()->withTrashed()->findOrFail($this->church->id);
        expect($church->trashed())->toBeTrue()
            ->and($church->purged_at)->not->toBeNull()
            ->and($church->address)->toBeNull()->and($church->phone)->toBeNull()->and($church->email)->toBeNull()
            ->and($church->payout_account_number)->toBeNull()->and($church->payout_account_name)->toBeNull()
            ->and($church->slug)->toBe("grace-deleted-{$church->id}")
            ->and($church->is_active)->toBeFalse();
        expect(fn () => Church::factory()->create(['slug' => 'grace']))->not->toThrow(Throwable::class);
    });

    it('deletes the church\'s stored files and nobody else\'s', function (): void {
        ($this->purge)();

        Storage::assertMissing("churches/{$this->church->id}/members/x/photo.jpg");
        Storage::assertMissing("churches/{$this->church->id}/exports/members.csv");
        Storage::assertExists("churches/{$this->other->id}/members/x/photo.jpg");
    });

    it('leaves every other church untouched', function (): void {
        ($this->purge)();

        $member = Member::query()->withoutGlobalScopes()->where('church_id', $this->other->id)->sole();
        $staff = User::query()->withoutGlobalScopes()->where('church_id', $this->other->id)->sole();
        expect($member->first_name)->toBe('Abena')->and($member->trashed())->toBeFalse()
            ->and($staff->name)->toBe('Kofi Mensah')->and($staff->tokens()->count())->toBe(1)
            ->and(MessageLog::query()->withoutGlobalScopes()->where('church_id', $this->other->id)->count())->toBe(1)
            ->and(Church::query()->find($this->other->id)->address)->toBe('1 Ring Road')
            ->and(Contribution::query()->withoutGlobalScopes()->where('church_id', $this->other->id)->sole()->notes)->toBe('Paid for Ama');
    });

    it('does not leave the church bound as the current church', function (): void {
        ($this->purge)();

        expect(app()->bound('current.church'))->toBeFalse();
    });

    it('can be run again without harm', function (): void {
        ($this->purge)();

        expect(fn () => app(PurgeChurchAction::class)->execute(new PurgeChurchDTO(Church::query()->withTrashed()->findOrFail($this->church->id))))->not->toThrow(Throwable::class);
        expect(Church::query()->withTrashed()->findOrFail($this->church->id)->slug)->toBe("grace-deleted-{$this->church->id}");
    });

    it('rolls everything back when a step fails', function (): void {
        $broken = Mockery::mock(EloquentChurchPurgeRepository::class)->makePartial();
        $broken->shouldReceive('deleteMessageLogs')->andThrow(new RuntimeException('database down'));
        app()->instance(ChurchPurgeRepositoryInterface::class, $broken);
        app()->forgetInstance(ChurchPurgeService::class);

        expect(fn () => ($this->purge)())->toThrow(RuntimeException::class);

        $member = Member::query()->withoutGlobalScopes()->where('church_id', $this->church->id)->sole();
        expect($member->first_name)->toBe('Abena')
            ->and(Church::query()->find($this->church->id)->purged_at)->toBeNull()
            ->and(app()->bound('current.church'))->toBeFalse();
    });
});

describe('the purge job', function (): void {
    it('purges the church it was given', function (): void {
        Storage::fake();
        $church = churchWithData('grace');

        (new PurgeChurchJob($church->id))->handle(app(PurgeChurchAction::class));

        expect(Church::query()->withTrashed()->find($church->id)->purged_at)->not->toBeNull();
    });

    it('skips a church that does not exist', function (): void {
        expect(fn () => (new PurgeChurchJob('01doesnotexist00000000000'))->handle(app(PurgeChurchAction::class)))->not->toThrow(Throwable::class);
    });

    it('is unique for each church, retries, and logs a failure as critical', function (): void {
        Log::spy();
        $job = new PurgeChurchJob('church-1');

        $job->failed(new RuntimeException('boom'));

        expect($job->uniqueId())->toBe('church-1')->and($job->tries)->toBe(3)->and($job->queue)->toBe('scheduled');
        Log::shouldHaveReceived('critical')->withArgs(fn (string $message, array $context): bool => $message === 'Church purge failed' && $context === ['church_id' => 'church-1', 'error' => RuntimeException::class]);
    });
});
