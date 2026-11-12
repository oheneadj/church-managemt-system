<?php

declare(strict_types=1);

use App\Actions\Contributions\BatchBankPayoutsAction;
use App\Actions\Contributions\SendDisbursementAction;
use App\Console\Commands\BatchBankPayouts;
use App\Contracts\DisbursementProvider;
use App\DTOs\Payments\DisbursementResult;
use App\DTOs\Payments\PayoutAccount;
use App\Enums\DisbursementStatus;
use App\Enums\MomoNetwork;
use App\Enums\PayoutMethod;
use App\Enums\ProviderPaymentStatus;
use App\Jobs\SendDisbursementJob;
use App\Models\Church;
use App\Models\Disbursement;
use App\Repositories\DisbursementRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;

// A church that settles to a bank is paid when its settled gifts reach the bank minimum, GHS 100 (D-072, D-069 step e3): the waiting
// payouts go into one batch, sent like any payout, and every gift in it is paid with it. Money code, so the sums and the edges are exact.

/** A bank church with a verified account. */
function bankChurch(string $slug): Church
{
    return Church::factory()->create([
        'slug' => $slug, 'payout_method' => PayoutMethod::Bank, 'payout_network' => null, 'payout_bank_code' => '058',
        'payout_account_number' => '0123456789', 'payout_account_name' => 'GRACE ASSEMBLY', 'payout_verified_at' => now(),
    ]);
}

/** A gift's own payout waiting for a batch, owed this much (the fee is a tenth of a percent of nothing: a flat GHS 0.10 here). */
function waiting(Church $church, string $requested, array $overrides = []): Disbursement
{
    return Disbursement::factory()->create([
        'church_id' => $church->id, 'gross_amount' => bcadd($requested, '0.10', 2), 'platform_fee' => '0.10', 'requested_amount' => $requested, ...$overrides,
    ]);
}

/** Makes the batches. */
function makeBatches(): int
{
    return app(BatchBankPayoutsAction::class)->execute();
}

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-11-12 12:00:00');
    Queue::fake();
    $this->church = bankChurch('grace');
});

afterEach(fn () => CarbonImmutable::setTestNow());

describe('making a batch', function (): void {
    it('puts the waiting payouts into one batch once they reach GHS 100, with the sums, and queues it on the payments queue', function (): void {
        $a = waiting($this->church, '60.00');
        $b = waiting($this->church, '45.00');

        expect(makeBatches())->toBe(1);

        $batch = Disbursement::query()->whereNull('contribution_id')->sole();
        expect($batch->church_id)->toBe($this->church->id)->and($batch->requested_amount)->toBe('105.00')->and($batch->gross_amount)->toBe('105.20')->and($batch->platform_fee)->toBe('0.20')
            ->and($batch->status)->toBe(DisbursementStatus::Pending)->and($batch->attempts)->toBe(0)->and($batch->batch_id)->toBeNull();
        expect($a->fresh()->batch_id)->toBe($batch->id)->and($b->fresh()->batch_id)->toBe($batch->id)
            ->and($a->fresh()->status)->toBe(DisbursementStatus::Processing)->and($b->fresh()->status)->toBe(DisbursementStatus::Processing);
        Queue::assertPushedOn('payments', SendDisbursementJob::class, fn (SendDisbursementJob $job) => $job->disbursementId === $batch->id);
    });

    it('takes exactly GHS 100.00, and waits one pesewa short', function (): void {
        waiting($this->church, '99.99');
        expect(makeBatches())->toBe(0)->and(Disbursement::query()->whereNull('contribution_id')->count())->toBe(0);

        waiting($this->church, '0.01');
        expect(makeBatches())->toBe(1)->and(Disbursement::query()->whereNull('contribution_id')->sole()->requested_amount)->toBe('100.00');
    });

    it('waits, with every gift still pending and no batch, while the total is under the minimum', function (): void {
        $a = waiting($this->church, '30.00');
        $b = waiting($this->church, '30.00');
        $c = waiting($this->church, '30.00');

        expect(makeBatches())->toBe(0);

        expect($a->fresh()->status)->toBe(DisbursementStatus::Pending)->and($b->fresh()->batch_id)->toBeNull()->and($c->fresh()->batch_id)->toBeNull();
        Queue::assertNothingPushed();
    });

    it('takes every waiting payout in one batch, and reads the minimum from the configuration', function (): void {
        waiting($this->church, '80.00');
        waiting($this->church, '120.00');
        waiting($this->church, '5.00');

        makeBatches();
        expect(Disbursement::query()->whereNull('contribution_id')->sole()->requested_amount)->toBe('205.00');

        config(['giving.bank_payout_minimum' => '500.00']);
        $other = bankChurch('second');
        waiting($other, '300.00');
        expect(makeBatches())->toBe(0);
    });

    it('puts a payout in at most one batch: a second run finds nothing, and paid or started payouts are never batched', function (): void {
        waiting($this->church, '150.00');
        waiting($this->church, '200.00', ['status' => DisbursementStatus::Paid]);
        waiting($this->church, '200.00', ['status' => DisbursementStatus::Processing, 'provider_reference' => 'pay-1']);
        waiting($this->church, '200.00', ['status' => DisbursementStatus::Failed]);

        expect(makeBatches())->toBe(1)->and(makeBatches())->toBe(0);

        expect(Disbursement::query()->whereNull('contribution_id')->count())->toBe(1)->and(Disbursement::query()->whereNull('contribution_id')->sole()->requested_amount)->toBe('150.00');
    });

    it('batches each church on its own and never mixes churches (isolation)', function (): void {
        $second = bankChurch('second');
        waiting($this->church, '70.00');
        waiting($second, '70.00');

        expect(makeBatches())->toBe(0);

        waiting($this->church, '40.00');
        waiting($second, '50.00');
        expect(makeBatches())->toBe(2);

        $ours = Disbursement::query()->withoutGlobalScopes()->whereNull('contribution_id')->where('church_id', $this->church->id)->sole();
        $theirs = Disbursement::query()->withoutGlobalScopes()->whereNull('contribution_id')->where('church_id', $second->id)->sole();
        expect($ours->requested_amount)->toBe('110.00')->and($theirs->requested_amount)->toBe('120.00');
    });

    it('is only for a bank church with a verified account', function (string $case): void {
        match ($case) {
            'mobile money' => $this->church->update(['payout_method' => PayoutMethod::Momo, 'payout_network' => MomoNetwork::Mtn, 'payout_bank_code' => null, 'payout_account_number' => '+233244000111']),
            'unverified' => $this->church->update(['payout_verified_at' => null]),
            'no account' => $this->church->update(['payout_method' => null, 'payout_bank_code' => null, 'payout_account_number' => null, 'payout_verified_at' => null]),
        };
        waiting($this->church, '500.00');

        expect(makeBatches())->toBe(0);
    })->with(['mobile money', 'unverified', 'no account']);

    it('is run by the command every minute', function (): void {
        waiting($this->church, '150.00');

        $this->artisan('giving:batch-bank-payouts')->assertSuccessful();

        expect(Disbursement::query()->whereNull('contribution_id')->count())->toBe(1);
        $event = collect(app(Schedule::class)->events())->first(fn ($event): bool => str_contains((string) $event->command, 'giving:batch-bank-payouts'));
        expect($event)->not->toBeNull()->and($event->expression)->toBe('* * * * *')->and(BatchBankPayouts::class)->toBeString();
    });
});

describe('sending a batch', function (): void {
    /** Fakes the provider for a batch. */
    function fakeBank(Disbursement $batch, ProviderPaymentStatus $status, ?int $calls = 1): void
    {
        $provider = Mockery::mock(DisbursementProvider::class);
        $provider->shouldReceive('availableBalance')->andReturn('5000.00');
        if ($calls === 0) {
            $provider->shouldNotReceive('disburse');
        } else {
            $provider->shouldReceive('disburse')->times($calls)
                ->with(Mockery::on(fn (PayoutAccount $a) => $a->method === PayoutMethod::Bank && $a->bankCode === '058' && $a->number === '0123456789' && $a->holderName === 'GRACE ASSEMBLY'), $batch->requested_amount, $batch->id.'-1')
                ->andReturn(new DisbursementResult('pay-'.$batch->id, $batch->id.'-1', $status, $batch->requested_amount, '1.67', '103.33'));
        }
        app()->instance(DisbursementProvider::class, $provider);
    }

    it('pays the bank account the whole batch with the batch\'s own id as the reference, and every gift in it is paid with it', function (): void {
        $a = waiting($this->church, '60.00');
        $b = waiting($this->church, '45.00');
        makeBatches();
        $batch = Disbursement::query()->whereNull('contribution_id')->sole();
        fakeBank($batch, ProviderPaymentStatus::Succeeded);

        $paid = app(SendDisbursementAction::class)->execute($batch->id);

        expect($paid->status)->toBe(DisbursementStatus::Paid)->and($paid->provider_fee)->toBe('1.67')->and($paid->net_amount)->toBe('103.33')->and($paid->attempts)->toBe(1);
        expect($a->fresh()->status)->toBe(DisbursementStatus::Paid)->and($b->fresh()->status)->toBe(DisbursementStatus::Paid)
            ->and($a->fresh()->paid_at)->not->toBeNull()->and($a->fresh()->batch_id)->toBe($batch->id);
    });

    it('leaves every gift in it started while the batch is not paid, and never sends a gift of a batch on its own', function (): void {
        $a = waiting($this->church, '60.00');
        waiting($this->church, '45.00');
        makeBatches();
        $batch = Disbursement::query()->whereNull('contribution_id')->sole();
        fakeBank($batch, ProviderPaymentStatus::Failed);
        app(SendDisbursementAction::class)->execute($batch->id);

        expect($batch->fresh()->status)->toBe(DisbursementStatus::Failed)->and($a->fresh()->status)->toBe(DisbursementStatus::Processing);

        $this->travel(1)->hours();
        fakeBank($batch, ProviderPaymentStatus::Failed, 0);
        app(SendDisbursementAction::class)->execute($a->id);
        expect($a->fresh()->attempts)->toBe(0);
    });

    it('never sends a gift of a batch on its own, even if the church has since changed to a mobile money wallet (no double payment)', function (): void {
        $a = waiting($this->church, '60.00');
        waiting($this->church, '45.00');
        makeBatches();
        $this->church->update(['payout_method' => PayoutMethod::Momo, 'payout_network' => MomoNetwork::Mtn, 'payout_bank_code' => null, 'payout_account_number' => '+233244000111']);
        $this->travel(1)->hours();
        $provider = Mockery::mock(DisbursementProvider::class);
        $provider->shouldReceive('availableBalance')->andReturn('5000.00');
        $provider->shouldNotReceive('disburse');
        app()->instance(DisbursementProvider::class, $provider);

        app(SendDisbursementAction::class)->execute($a->id);

        expect($a->fresh()->attempts)->toBe(0)->and($a->fresh()->status)->toBe(DisbursementStatus::Processing);
    });

    it('leaves the sweep out of every gift that is inside a batch', function (): void {
        $a = waiting($this->church, '60.00');
        waiting($this->church, '45.00');
        makeBatches();
        $batch = Disbursement::query()->whereNull('contribution_id')->sole();
        $this->travel(1)->hours();

        $ids = app(DisbursementRepositoryInterface::class)->dueIds(now(), 5, 5, 2);

        expect($ids->all())->toBe([$batch->id])->and($ids)->not->toContain($a->id);
    });

    it('is still sent to a church that has since changed to a mobile money wallet, as one payout', function (): void {
        waiting($this->church, '60.00');
        waiting($this->church, '45.00');
        makeBatches();
        $batch = Disbursement::query()->whereNull('contribution_id')->sole();
        $this->church->update(['payout_method' => PayoutMethod::Momo, 'payout_network' => MomoNetwork::Mtn, 'payout_bank_code' => null, 'payout_account_number' => '+233244000111']);
        $provider = Mockery::mock(DisbursementProvider::class);
        $provider->shouldReceive('availableBalance')->andReturn('5000.00');
        $provider->shouldReceive('disburse')->once()->with(Mockery::on(fn (PayoutAccount $a) => $a->method === PayoutMethod::Momo), '105.00', $batch->id.'-1')
            ->andReturn(new DisbursementResult('pay-1', $batch->id.'-1', ProviderPaymentStatus::Succeeded, '105.00', null, null));
        app()->instance(DisbursementProvider::class, $provider);

        expect(app(SendDisbursementAction::class)->execute($batch->id)->status)->toBe(DisbursementStatus::Paid);
    });
});
