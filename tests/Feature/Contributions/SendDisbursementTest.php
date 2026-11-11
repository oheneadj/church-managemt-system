<?php

declare(strict_types=1);

use App\Actions\Contributions\SendDisbursementAction;
use App\Actions\Contributions\SettleContributionAction;
use App\Console\Commands\SendDuePayouts;
use App\Contracts\DisbursementProvider;
use App\DTOs\Contributions\SettleContributionDTO;
use App\DTOs\Payments\CollectionResult;
use App\DTOs\Payments\DisbursementResult;
use App\DTOs\Payments\PayoutAccount;
use App\Enums\DisbursementStatus;
use App\Enums\MomoNetwork;
use App\Enums\PayoutMethod;
use App\Enums\ProviderPaymentStatus;
use App\Exceptions\PaymentProviderUnavailableException;
use App\Exceptions\PaymentRejectedException;
use App\Jobs\SendDisbursementJob;
use App\Models\Church;
use App\Models\Contribution;
use App\Models\Disbursement;
use App\Models\FundType;
use App\Repositories\DisbursementRepositoryInterface;
use App\Repositories\EloquentDisbursementRepository;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;

// Sending a church the money it is owed (D-018, D-069 step e2): the requested amount goes to the church's verified wallet with the
// payout's own id as the reference, only when the provider keeps its minimum balance, claimed under a lock so it is sent once, and a
// failure is tried again and then raised as an alert. Money code, so every branch is tested. The provider is faked.

/** What the provider says about a payout. */
function payoutSays(Disbursement $payout, ProviderPaymentStatus $status, ?string $amount = null, ?string $fee = '1.57', ?string $received = '97.43', ?string $clientReference = null): DisbursementResult
{
    return new DisbursementResult('pay-'.$payout->id, $clientReference ?? $payout->id, $status, $amount ?? $payout->requested_amount, $fee, $received);
}

/** Fakes the provider: its balance, and what it does when asked to pay. */
function fakeProvider(string|Throwable $balance, DisbursementResult|Throwable|null $payout = null, int $payoutCalls = 1): MockInterface
{
    $provider = Mockery::mock(DisbursementProvider::class);
    $provider->shouldReceive('availableBalance')->andReturnUsing(fn () => $balance instanceof Throwable ? throw $balance : $balance);

    if ($payout === null) {
        $provider->shouldNotReceive('disburse');
    } else {
        $provider->shouldReceive('disburse')->times($payoutCalls)->andReturnUsing(fn () => $payout instanceof Throwable ? throw $payout : $payout);
    }

    app()->instance(DisbursementProvider::class, $provider);

    return $provider;
}

/** Sends a payout. */
function sendPayout(Disbursement $payout): ?Disbursement
{
    return app(SendDisbursementAction::class)->execute($payout->id);
}

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-11-11 12:00:00');
    $this->church = Church::factory()->create([
        'slug' => 'grace', 'payout_method' => PayoutMethod::Momo, 'payout_network' => MomoNetwork::Mtn,
        'payout_account_number' => '+233244000111', 'payout_account_name' => 'GRACE ASSEMBLY', 'payout_verified_at' => now(),
    ]);
    $this->other = Church::factory()->create(['slug' => 'other']);
    $this->fund = FundType::factory()->create(['church_id' => $this->church->id]);
    $this->gift = Contribution::factory()->create(['church_id' => $this->church->id, 'fund_type_id' => $this->fund->id, 'amount' => '100.00', 'recorded_by' => null]);
    $this->payout = Disbursement::factory()->create(['church_id' => $this->church->id, 'contribution_id' => $this->gift->id, 'gross_amount' => '100.00', 'platform_fee' => '1.00', 'requested_amount' => '99.00']);
});

afterEach(fn () => CarbonImmutable::setTestNow());

describe('sending the money', function (): void {
    it('pays the requested amount to the verified wallet, with the payout\'s own id as the reference, and records what the church received', function (): void {
        $provider = Mockery::mock(DisbursementProvider::class);
        $provider->shouldReceive('availableBalance')->andReturn('500.00');
        $provider->shouldReceive('disburse')->once()
            ->with(Mockery::on(fn (PayoutAccount $a) => $a->holderName === 'GRACE ASSEMBLY' && $a->number === '+233244000111' && $a->network === MomoNetwork::Mtn), '99.00', $this->payout->id)
            ->andReturn(payoutSays($this->payout, ProviderPaymentStatus::Succeeded));
        app()->instance(DisbursementProvider::class, $provider);

        $paid = sendPayout($this->payout);

        expect($paid->status)->toBe(DisbursementStatus::Paid)->and($paid->attempts)->toBe(1)->and($paid->provider_reference)->toBe('pay-'.$this->payout->id)
            ->and($paid->provider_fee)->toBe('1.57')->and($paid->net_amount)->toBe('97.43')->and($paid->paid_at)->not->toBeNull()->and($paid->failure_reason)->toBeNull();
    });

    it('works out what the church received from the fee when the provider gives no figure, and leaves it empty when it gives neither', function (): void {
        fakeProvider('500.00', payoutSays($this->payout, ProviderPaymentStatus::Succeeded, null, '1.57', null));
        expect(sendPayout($this->payout)->net_amount)->toBe('97.43');

        $second = Disbursement::factory()->create(['church_id' => $this->church->id, 'requested_amount' => '10.00']);
        fakeProvider('500.00', payoutSays($second, ProviderPaymentStatus::Succeeded, null, null, null));
        $paid = sendPayout($second);
        expect($paid->provider_fee)->toBeNull()->and($paid->net_amount)->toBeNull()->and($paid->status)->toBe(DisbursementStatus::Paid);
    });

    it('is left started, with the reference, when the provider is not finished', function (ProviderPaymentStatus $status): void {
        fakeProvider('500.00', payoutSays($this->payout, $status));

        $started = sendPayout($this->payout);

        expect($started->status)->toBe(DisbursementStatus::Processing)->and($started->provider_reference)->toBe('pay-'.$this->payout->id)->and($started->attempts)->toBe(1)->and($started->paid_at)->toBeNull();
    })->with([ProviderPaymentStatus::Pending, ProviderPaymentStatus::Unknown]);

    it('is sent once however many times it is asked (a second run finds it started)', function (): void {
        fakeProvider('500.00', payoutSays($this->payout, ProviderPaymentStatus::Pending), 1);

        sendPayout($this->payout);
        sendPayout($this->payout);
        sendPayout($this->payout);

        expect($this->payout->fresh()->attempts)->toBe(1);
    });

    it('is not sent a second time by a worker that read it before another worker took it (the claim re-checks under the lock)', function (): void {
        $stale = Disbursement::query()->find($this->payout->id);
        $this->payout->forceFill(['status' => DisbursementStatus::Processing, 'provider_reference' => 'pay-1', 'attempts' => 1])->save();
        $repository = Mockery::mock(EloquentDisbursementRepository::class)->makePartial();
        $repository->shouldReceive('find')->andReturn($stale);
        app()->instance(DisbursementRepositoryInterface::class, $repository);
        fakeProvider('500.00');

        app(SendDisbursementAction::class)->execute($this->payout->id);

        expect($this->payout->fresh()->attempts)->toBe(1);
    });

    it('is not marked paid when the answer is for another payout or another amount, and is logged for a person', function (string $case): void {
        Log::spy();
        $answer = match ($case) {
            'another reference' => payoutSays($this->payout, ProviderPaymentStatus::Succeeded, null, '1.57', '97.43', 'someone-elses'),
            'another amount' => payoutSays($this->payout, ProviderPaymentStatus::Succeeded, '50.00'),
        };
        fakeProvider('500.00', $answer);

        $result = sendPayout($this->payout);

        expect($result->status)->toBe(DisbursementStatus::Processing)->and($result->paid_at)->toBeNull();
        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $m, array $c) => $c === ['disbursement_id' => $this->payout->id, 'church_id' => $this->church->id]);
    })->with(['another reference', 'another amount']);
});

describe('the provider\'s minimum balance (GHS 1.00)', function (): void {
    it('waits, pending and untouched, when the payout would leave less than the minimum', function (): void {
        fakeProvider('99.50');

        $waiting = sendPayout($this->payout);

        expect($waiting->status)->toBe(DisbursementStatus::Pending)->and($waiting->attempts)->toBe(0);
    });

    it('sends when the payout leaves exactly the minimum, and waits one pesewa short', function (): void {
        fakeProvider('99.99');
        expect(sendPayout($this->payout)->status)->toBe(DisbursementStatus::Pending);

        fakeProvider('100.00', payoutSays($this->payout, ProviderPaymentStatus::Succeeded));
        expect(sendPayout($this->payout)->status)->toBe(DisbursementStatus::Paid);
    });

    it('reads the minimum from the configuration', function (): void {
        config(['giving.payout_minimum_balance' => '5.00']);
        fakeProvider('100.00');

        expect(sendPayout($this->payout)->status)->toBe(DisbursementStatus::Pending);
    });

    it('waits, and logs without any amount, when the provider will not give its balance', function (): void {
        Log::spy();
        fakeProvider(new PaymentRejectedException('no balance'));

        expect(sendPayout($this->payout)->status)->toBe(DisbursementStatus::Pending);
        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $m, array $c) => $c === ['disbursement_id' => $this->payout->id, 'church_id' => $this->church->id]);
    });
});

describe('when it fails', function (): void {
    it('is marked failed with the reason when the provider says the payout did not go through, and is not an alert yet', function (): void {
        Log::spy();
        fakeProvider('500.00', payoutSays($this->payout, ProviderPaymentStatus::Failed));

        $failed = sendPayout($this->payout);

        expect($failed->status)->toBe(DisbursementStatus::Failed)->and($failed->failure_reason)->toBe('The provider said the payout did not go through.')->and($failed->attempts)->toBe(1);
        Log::shouldNotHaveReceived('critical');
    });

    it('is marked failed with the provider\'s refusal, and raises an alert once the attempts are used up', function (): void {
        Log::spy();
        $this->payout->forceFill(['status' => DisbursementStatus::Failed, 'attempts' => 4])->save();
        $this->travel(6)->minutes();
        fakeProvider('500.00', new PaymentRejectedException('PayPerlez refused: account closed'));

        $failed = sendPayout($this->payout);

        expect($failed->status)->toBe(DisbursementStatus::Failed)->and($failed->attempts)->toBe(5)->and($failed->failure_reason)->toBe('PayPerlez refused: account closed');
        Log::shouldHaveReceived('critical')->once()->withArgs(fn (string $m, array $c) => $c === ['disbursement_id' => $this->payout->id, 'church_id' => $this->church->id, 'attempts' => 5]);
    });

    it('is tried again after the wait, and not before, and never past the attempts allowed', function (): void {
        $this->payout->forceFill(['status' => DisbursementStatus::Failed, 'attempts' => 2])->save();
        fakeProvider('500.00');
        expect(sendPayout($this->payout)->status)->toBe(DisbursementStatus::Failed);

        $this->travel(6)->minutes();
        fakeProvider('500.00', payoutSays($this->payout, ProviderPaymentStatus::Succeeded));
        expect(sendPayout($this->payout)->status)->toBe(DisbursementStatus::Paid);

        $spent = Disbursement::factory()->create(['church_id' => $this->church->id, 'status' => DisbursementStatus::Failed, 'attempts' => 5]);
        $this->travel(1)->hours();
        fakeProvider('500.00');
        expect(sendPayout($spent)->attempts)->toBe(5);
    });

    it('is left started when the provider cannot be reached, and picked up again after the stuck wait', function (): void {
        fakeProvider('500.00', new PaymentProviderUnavailableException('down'));
        expect(fn () => sendPayout($this->payout))->toThrow(PaymentProviderUnavailableException::class);
        expect($this->payout->fresh()->status)->toBe(DisbursementStatus::Processing)->and($this->payout->fresh()->provider_reference)->toBeNull();

        fakeProvider('500.00');
        expect(sendPayout($this->payout)->attempts)->toBe(1);

        $this->travel(3)->minutes();
        fakeProvider('500.00', payoutSays($this->payout, ProviderPaymentStatus::Succeeded));
        expect(sendPayout($this->payout)->status)->toBe(DisbursementStatus::Paid)->and($this->payout->fresh()->attempts)->toBe(2);
    });
});

describe('what is never sent', function (): void {
    it('is nothing for a payout that is paid, started with a reference, or gone', function (): void {
        fakeProvider('500.00');
        $this->payout->forceFill(['status' => DisbursementStatus::Paid])->save();
        sendPayout($this->payout);
        $this->payout->forceFill(['status' => DisbursementStatus::Processing, 'provider_reference' => 'pay-1'])->save();
        $this->travel(1)->hours();
        sendPayout($this->payout);

        expect(app(SendDisbursementAction::class)->execute('01ARZ3NDEKTSV4RRFFQ69G5FAV'))->toBeNull();
    });

    it('is nothing while the church has no verified account, or settles to a bank (D-072), or has changed its account', function (string $case): void {
        match ($case) {
            'unverified' => $this->church->update(['payout_verified_at' => null]),
            'bank' => $this->church->update(['payout_method' => PayoutMethod::Bank, 'payout_network' => null, 'payout_bank_code' => '058', 'payout_account_number' => '0123456789']),
            'no account' => $this->church->update(['payout_method' => null, 'payout_network' => null, 'payout_account_number' => null, 'payout_verified_at' => null]),
        };
        fakeProvider('500.00');

        expect(sendPayout($this->payout)->status)->toBe(DisbursementStatus::Pending);
    })->with(['unverified', 'bank', 'no account']);
});

describe('the every-minute sweep and the job', function (): void {
    it('queues a send, on the payments queue, only for payouts that are due, in every church', function (): void {
        Queue::fake();
        $this->payout->forceFill(['created_at' => now()->subMinutes(10)])->save();
        $theirFund = FundType::factory()->create(['church_id' => $this->other->id]);
        $theirGift = Contribution::factory()->create(['church_id' => $this->other->id, 'fund_type_id' => $theirFund->id]);
        $theirs = Disbursement::factory()->create(['church_id' => $this->other->id, 'contribution_id' => $theirGift->id]);
        $paid = Disbursement::factory()->create(['church_id' => $this->church->id, 'status' => DisbursementStatus::Paid]);
        $failedDue = Disbursement::factory()->create(['church_id' => $this->church->id, 'status' => DisbursementStatus::Failed, 'attempts' => 2, 'updated_at' => now()->subMinutes(6)]);
        $failedFresh = Disbursement::factory()->create(['church_id' => $this->church->id, 'status' => DisbursementStatus::Failed, 'attempts' => 2, 'updated_at' => now()->subMinute()]);
        $failedSpent = Disbursement::factory()->create(['church_id' => $this->church->id, 'status' => DisbursementStatus::Failed, 'attempts' => 5, 'updated_at' => now()->subHour()]);
        $stuck = Disbursement::factory()->create(['church_id' => $this->church->id, 'status' => DisbursementStatus::Processing, 'updated_at' => now()->subMinutes(3)]);
        $working = Disbursement::factory()->create(['church_id' => $this->church->id, 'status' => DisbursementStatus::Processing, 'provider_reference' => 'pay-9', 'updated_at' => now()->subHour()]);

        $this->artisan('giving:send-due-payouts')->assertSuccessful();

        Queue::assertPushedOn('payments', SendDisbursementJob::class);
        $queued = Queue::pushed(SendDisbursementJob::class)->map(fn (SendDisbursementJob $job): string => $job->disbursementId)->sort()->values()->all();
        expect($queued)->toBe(collect([$this->payout->id, $theirs->id, $failedDue->id, $stuck->id])->sort()->values()->all())
            ->and($queued)->not->toContain($paid->id)->not->toContain($failedFresh->id)->not->toContain($failedSpent->id)->not->toContain($working->id);
    });

    it('is run every minute', function (): void {
        $event = collect(app(Schedule::class)->events())->first(fn ($event): bool => str_contains((string) $event->command, 'giving:send-due-payouts'));

        expect($event)->not->toBeNull()->and($event->expression)->toBe('* * * * *')->and(SendDuePayouts::class)->toBeString();
    });

    it('sends through the job, and logs ids only when the job fails', function (): void {
        fakeProvider('500.00', payoutSays($this->payout, ProviderPaymentStatus::Succeeded));
        (new SendDisbursementJob($this->payout->id))->handle(app(SendDisbursementAction::class));
        expect($this->payout->fresh()->status)->toBe(DisbursementStatus::Paid);

        Log::spy();
        (new SendDisbursementJob($this->payout->id))->failed(new RuntimeException('boom'));
        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $m, array $c) => $c === ['disbursement_id' => $this->payout->id, 'error' => RuntimeException::class]);
    });

    it('is queued, after the commit, when a gift completes', function (): void {
        Queue::fake();
        $gift = Contribution::factory()->pending()->create(['church_id' => $this->church->id, 'fund_type_id' => $this->fund->id, 'amount' => '100.00', 'total_paid' => '101.99', 'recorded_by' => null]);

        app(SettleContributionAction::class)->execute(new SettleContributionDTO($gift->id, new CollectionResult('ppz-'.$gift->id, $gift->id, ProviderPaymentStatus::Succeeded, '101.99', null, null)));

        $queued = Queue::pushed(SendDisbursementJob::class);
        expect($queued)->toHaveCount(1)->and($queued->first()->disbursementId)->toBe(Disbursement::query()->where('contribution_id', $gift->id)->value('id'));
        Queue::assertPushedOn('payments', SendDisbursementJob::class);
    });
});
