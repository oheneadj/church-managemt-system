<?php

declare(strict_types=1);

use App\Actions\Contributions\SettleContributionAction;
use App\Console\Commands\CheckPendingGifts;
use App\Contracts\PaymentProvider;
use App\DTOs\Contributions\SettleContributionDTO;
use App\DTOs\Payments\CollectionResult;
use App\Enums\ContributionStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProviderPaymentStatus;
use App\Events\ContributionReceived;
use App\Exceptions\PaymentProviderUnavailableException;
use App\Exceptions\PaymentRejectedException;
use App\Jobs\CheckContributionPaymentJob;
use App\Models\AuditLog;
use App\Models\Church;
use App\Models\Contribution;
use App\Models\FundType;
use App\Models\Member;
use App\Models\Pledge;
use App\Models\User;
use App\Repositories\ContributionRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

// Settling a mobile money gift (D-053, D-069 step d3c, agreement sections 10 and 15): the provider's final word makes a pending gift
// completed or failed, once, however many times it is told; only the very payment and total asked for can complete it; and a
// check every minute asks about gifts still pending. Money code, so every outcome is tested. The provider is faked.

/** What the provider says about a gift's payment. */
function providerSays(Contribution $gift, ProviderPaymentStatus $status, ?string $amount = null, ?string $clientReference = null): CollectionResult
{
    return new CollectionResult('ppz-'.$gift->id, $clientReference ?? $gift->id, $status, $amount ?? (string) $gift->total_paid, '2.03', null);
}

/** Settles a gift with an answer. */
function settle(Contribution $gift, CollectionResult $result): ?Contribution
{
    return app(SettleContributionAction::class)->execute(new SettleContributionDTO($gift->id, $result));
}

beforeEach(function (): void {
    $this->church = Church::factory()->create(['slug' => 'grace']);
    $this->other = Church::factory()->create(['slug' => 'other']);
    $this->fund = FundType::factory()->create(['church_id' => $this->church->id]);
    $this->gift = Contribution::factory()->pending()->create([
        'church_id' => $this->church->id, 'fund_type_id' => $this->fund->id, 'amount' => '100.00', 'processing_fee' => '1.99', 'total_paid' => '101.99', 'recorded_by' => null,
    ]);
});

describe('a paid gift', function (): void {
    it('becomes completed, keeps the provider\'s reference, and fires the receipt event once', function (): void {
        Event::fake([ContributionReceived::class]);

        $settled = settle($this->gift, providerSays($this->gift, ProviderPaymentStatus::Succeeded));

        expect($settled->status)->toBe(ContributionStatus::Completed)->and($this->gift->fresh()->payperlez_reference)->toBe('ppz-'.$this->gift->id);
        Event::assertDispatchedTimes(ContributionReceived::class, 1);
    });

    it('counts from then on, and not before', function (): void {
        expect(Contribution::query()->counted()->count())->toBe(0);

        settle($this->gift, providerSays($this->gift, ProviderPaymentStatus::Succeeded));

        expect(Contribution::query()->counted()->count())->toBe(1);
    });

    it('is counted once however many times the provider says it (a webhook and a check together, a redelivery)', function (): void {
        Event::fake([ContributionReceived::class]);

        settle($this->gift, providerSays($this->gift, ProviderPaymentStatus::Succeeded));
        settle($this->gift, providerSays($this->gift, ProviderPaymentStatus::Succeeded));
        settle($this->gift, providerSays($this->gift, ProviderPaymentStatus::Succeeded));

        Event::assertDispatchedTimes(ContributionReceived::class, 1);
        expect(Contribution::query()->counted()->count())->toBe(1);
    });

    it('pays the pledge it was given to, with exact money', function (): void {
        $member = Member::factory()->create(['church_id' => $this->church->id]);
        $pledge = Pledge::factory()->create(['church_id' => $this->church->id, 'member_id' => $member->id, 'fund_type_id' => $this->fund->id, 'amount_pledged' => '500.00', 'amount_paid' => '0.00']);
        $this->gift->update(['pledge_id' => $pledge->id, 'member_id' => $member->id]);

        settle($this->gift, providerSays($this->gift, ProviderPaymentStatus::Succeeded));

        expect($pledge->fresh()->amount_paid)->toBe('100.00');
    });

    it('is written to the change history as the staff member who started it, but a public gift writes none', function (): void {
        $staff = User::factory()->for($this->church)->create();
        $started = Contribution::factory()->pending()->create(['church_id' => $this->church->id, 'fund_type_id' => $this->fund->id, 'total_paid' => '51.00', 'amount' => '50.00', 'recorded_by' => $staff->id]);

        settle($this->gift, providerSays($this->gift, ProviderPaymentStatus::Succeeded));
        expect(AuditLog::query()->count())->toBe(0);

        settle($started, providerSays($started, ProviderPaymentStatus::Succeeded));
        expect(AuditLog::query()->sole()->actor_id)->toBe($staff->id);
    });

    it('is NOT counted when the provider\'s answer is for another payment or another total', function (string $case): void {
        Event::fake([ContributionReceived::class]);
        Log::spy();
        $result = match ($case) {
            'another reference' => providerSays($this->gift, ProviderPaymentStatus::Succeeded, null, 'someone-elses-id'),
            'a smaller total' => providerSays($this->gift, ProviderPaymentStatus::Succeeded, '100.00'),
            'a larger total' => providerSays($this->gift, ProviderPaymentStatus::Succeeded, '150.00'),
        };

        $settled = settle($this->gift, $result);

        expect($settled->status)->toBe(ContributionStatus::Pending);
        Event::assertNotDispatched(ContributionReceived::class);
        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $m, array $c) => $c === ['contribution_id' => $this->gift->id, 'church_id' => $this->church->id]);
    })->with(['another reference', 'a smaller total', 'a larger total']);
});

describe('a gift that did not go through', function (): void {
    it('becomes failed, is never counted, and nothing follows', function (): void {
        Event::fake([ContributionReceived::class]);

        $settled = settle($this->gift, providerSays($this->gift, ProviderPaymentStatus::Failed));

        expect($settled->status)->toBe(ContributionStatus::Failed)->and(Contribution::query()->counted()->count())->toBe(0);
        Event::assertNotDispatched(ContributionReceived::class);
    });

    it('stays failed if the provider later says paid, and that is logged for a person to look into (nothing is quietly counted)', function (): void {
        Log::spy();
        settle($this->gift, providerSays($this->gift, ProviderPaymentStatus::Failed));

        settle($this->gift, providerSays($this->gift, ProviderPaymentStatus::Succeeded));

        expect($this->gift->fresh()->status)->toBe(ContributionStatus::Failed);
        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $m, array $c) => $c === ['contribution_id' => $this->gift->id, 'church_id' => $this->church->id]);
    });
});

describe('an answer that is not final', function (): void {
    it('leaves the gift pending', function (ProviderPaymentStatus $status): void {
        Event::fake([ContributionReceived::class]);

        $settled = settle($this->gift, providerSays($this->gift, $status));

        expect($settled->status)->toBe(ContributionStatus::Pending);
        Event::assertNotDispatched(ContributionReceived::class);
    })->with([ProviderPaymentStatus::Pending, ProviderPaymentStatus::Unknown]);

    it('does nothing for a gift that is gone, and never touches cash', function (): void {
        expect(app(SettleContributionAction::class)->execute(new SettleContributionDTO('01ARZ3NDEKTSV4RRFFQ69G5FAV', providerSays($this->gift, ProviderPaymentStatus::Succeeded))))->toBeNull();

        $cash = Contribution::factory()->create(['church_id' => $this->church->id, 'fund_type_id' => $this->fund->id, 'payment_method' => PaymentMethod::Cash]);
        expect(settle($cash, providerSays($cash, ProviderPaymentStatus::Failed))->status)->toBe(ContributionStatus::Completed);
    });
});

describe('the check on one gift', function (): void {
    /** Runs the job with a provider that says what it is given. */
    function runCheck(Contribution $gift, CollectionResult|Throwable $answer): void
    {
        $provider = Mockery::mock(PaymentProvider::class);
        $provider->shouldReceive('collectionStatus')->with($gift->id)->andReturnUsing(fn () => $answer instanceof Throwable ? throw $answer : $answer);
        (new CheckContributionPaymentJob($gift->id))->handle(app(ContributionRepositoryInterface::class), $provider, app(SettleContributionAction::class));
    }

    it('asks the provider by the gift\'s own id and settles with the answer', function (): void {
        runCheck($this->gift, providerSays($this->gift, ProviderPaymentStatus::Succeeded));

        expect($this->gift->fresh()->status)->toBe(ContributionStatus::Completed);
    });

    it('fails the gift when the provider says it failed', function (): void {
        runCheck($this->gift, providerSays($this->gift, ProviderPaymentStatus::Failed));

        expect($this->gift->fresh()->status)->toBe(ContributionStatus::Failed);
    });

    it('leaves the gift pending and logs without any person or amount when the provider refuses the question', function (): void {
        Log::spy();

        runCheck($this->gift, new PaymentRejectedException('no such payment'));

        expect($this->gift->fresh()->status)->toBe(ContributionStatus::Pending);
        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $m, array $c) => $c === ['contribution_id' => $this->gift->id, 'church_id' => $this->church->id]);
    });

    it('lets the job try again when the provider is busy', function (): void {
        expect(fn () => runCheck($this->gift, new PaymentProviderUnavailableException('down')))->toThrow(PaymentProviderUnavailableException::class);
        expect($this->gift->fresh()->status)->toBe(ContributionStatus::Pending);
    });

    it('skips a gift that is gone or already settled, asking the provider nothing', function (): void {
        $provider = Mockery::mock(PaymentProvider::class);
        $provider->shouldNotReceive('collectionStatus');
        $run = fn (string $id) => (new CheckContributionPaymentJob($id))->handle(app(ContributionRepositoryInterface::class), $provider, app(SettleContributionAction::class));

        $run('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $this->gift->update(['status' => ContributionStatus::Completed]);
        $run($this->gift->id);

        expect(true)->toBeTrue();
    });

    it('logs that the tries ran out, without any person or amount', function (): void {
        Log::spy();

        (new CheckContributionPaymentJob($this->gift->id))->failed(new RuntimeException('boom'));

        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $m, array $c) => $c === ['contribution_id' => $this->gift->id, 'error' => RuntimeException::class]);
    });
});

describe('the every-minute check', function (): void {
    beforeEach(function (): void {
        CarbonImmutable::setTestNow('2026-11-11 12:00:00');
        Queue::fake();
    });

    afterEach(fn () => CarbonImmutable::setTestNow());

    it('queues a check, on the payments queue, only for mobile money gifts that are pending and old enough, in every church', function (): void {
        $this->gift->forceFill(['created_at' => now()->subMinutes(5)])->save();
        $fresh = Contribution::factory()->pending()->create(['church_id' => $this->church->id, 'fund_type_id' => $this->fund->id, 'created_at' => now()->subSeconds(5)]);
        $done = Contribution::factory()->create(['church_id' => $this->church->id, 'fund_type_id' => $this->fund->id, 'payment_method' => PaymentMethod::Momo, 'created_at' => now()->subMinutes(5)]);
        $failed = Contribution::factory()->failed()->create(['church_id' => $this->church->id, 'fund_type_id' => $this->fund->id, 'created_at' => now()->subMinutes(5)]);
        $cash = Contribution::factory()->create(['church_id' => $this->church->id, 'fund_type_id' => $this->fund->id, 'created_at' => now()->subMinutes(5)]);
        $theirFund = FundType::factory()->create(['church_id' => $this->other->id]);
        $theirs = Contribution::factory()->pending()->create(['church_id' => $this->other->id, 'fund_type_id' => $theirFund->id, 'created_at' => now()->subMinutes(5)]);

        $this->artisan('giving:check-pending-gifts')->assertSuccessful();

        Queue::assertPushedOn('payments', CheckContributionPaymentJob::class);
        $queued = Queue::pushed(CheckContributionPaymentJob::class)->map(fn (CheckContributionPaymentJob $job): string => $job->contributionId)->sort()->values()->all();
        expect($queued)->toBe(collect([$this->gift->id, $theirs->id])->sort()->values()->all())
            ->and($queued)->not->toContain($fresh->id)->not->toContain($done->id)->not->toContain($failed->id)->not->toContain($cash->id);
    });

    it('reports gifts pending for too long, and stays quiet otherwise', function (): void {
        Log::spy();
        $this->gift->forceFill(['created_at' => now()->subMinutes(30)])->save();

        $this->artisan('giving:check-pending-gifts')->assertSuccessful();
        Log::shouldNotHaveReceived('warning');

        $this->gift->forceFill(['created_at' => now()->subMinutes(61)])->save();
        $this->artisan('giving:check-pending-gifts')->assertSuccessful();

        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $m, array $c): bool => $m === 'Mobile money gifts have been pending for too long' && $c === ['count' => 1]);
    });

    it('is run every minute', function (): void {
        $event = collect(app(Schedule::class)->events())->first(fn ($event): bool => str_contains((string) $event->command, 'giving:check-pending-gifts'));

        expect($event)->not->toBeNull()->and($event->expression)->toBe('* * * * *')->and(CheckPendingGifts::class)->toBeString();
    });
});
