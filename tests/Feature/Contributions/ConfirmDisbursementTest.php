<?php

declare(strict_types=1);

use App\Actions\Contributions\ConfirmDisbursementAction;
use App\Console\Commands\CheckStartedPayouts;
use App\Contracts\DisbursementProvider;
use App\DTOs\Payments\DisbursementResult;
use App\Enums\DisbursementStatus;
use App\Enums\ProviderPaymentStatus;
use App\Exceptions\PaymentProviderUnavailableException;
use App\Exceptions\PaymentRejectedException;
use App\Jobs\CheckDisbursementJob;
use App\Models\Church;
use App\Models\Disbursement;
use App\Repositories\DisbursementRepositoryInterface;
use App\Repositories\EloquentDisbursementRepository;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

// Asking the provider where a payout it has taken stands, and recording the answer (D-069 step e4): paid only when the provider names this
// very attempt and amount, failed when it says so, left alone when not final, and a payout that is paid is never changed. Money code, so every
// branch is tested. The provider is faked.

/** A payout the provider has taken but not finished. */
function startedPayout(array $overrides = []): Disbursement
{
    return Disbursement::factory()->create([
        'church_id' => test()->church->id, 'requested_amount' => '99.00', 'status' => DisbursementStatus::Processing, 'attempts' => 1,
        'provider_reference' => 'pay-'.Str::random(8), 'updated_at' => now()->subMinutes(2), ...$overrides,
    ]);
}

/** Fakes the provider's answer to "where does this payout stand". */
function providerStatus(Disbursement $payout, ProviderPaymentStatus|Throwable $answer, ?string $clientReference = null, ?string $amount = null): void
{
    $provider = Mockery::mock(DisbursementProvider::class);
    $provider->shouldReceive('disbursementStatus')->with($payout->provider_reference)->andReturnUsing(
        fn () => $answer instanceof Throwable ? throw $answer : new DisbursementResult($payout->provider_reference, $clientReference ?? $payout->id.'-'.$payout->attempts, $answer, $amount ?? $payout->requested_amount, '1.57', '97.43')
    );
    app()->instance(DisbursementProvider::class, $provider);
}

/** Confirms a payout. */
function confirmPayout(Disbursement $payout): ?Disbursement
{
    return app(ConfirmDisbursementAction::class)->execute($payout->id);
}

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-11-12 12:00:00');
    $this->church = Church::factory()->create(['slug' => 'grace']);
    $this->other = Church::factory()->create(['slug' => 'other']);
});

afterEach(fn () => CarbonImmutable::setTestNow());

describe('asking where a started payout stands', function (): void {
    it('marks it paid, with what the provider charged and what the church received, when the provider says it succeeded', function (): void {
        $payout = startedPayout();
        providerStatus($payout, ProviderPaymentStatus::Succeeded);

        $paid = confirmPayout($payout);

        expect($paid->status)->toBe(DisbursementStatus::Paid)->and($paid->provider_fee)->toBe('1.57')->and($paid->net_amount)->toBe('97.43')->and($paid->paid_at)->not->toBeNull();
    });

    it('marks it failed, with the reason, when the provider says it failed, and it is tried again as a new attempt', function (): void {
        $payout = startedPayout();
        providerStatus($payout, ProviderPaymentStatus::Failed);

        $failed = confirmPayout($payout);

        expect($failed->status)->toBe(DisbursementStatus::Failed)->and($failed->failure_reason)->toBe('The provider said the payout did not go through.')->and($failed->attempts)->toBe(1);
    });

    it('raises the alert when it fails on the last attempt', function (): void {
        Log::spy();
        $payout = startedPayout(['attempts' => 5]);
        providerStatus($payout, ProviderPaymentStatus::Failed);

        confirmPayout($payout);

        Log::shouldHaveReceived('critical')->once()->withArgs(fn (string $m, array $c) => $c === ['disbursement_id' => $payout->id, 'church_id' => $this->church->id, 'attempts' => 5]);
    });

    it('leaves it as it was while the provider is still working on it', function (ProviderPaymentStatus $status): void {
        $payout = startedPayout();
        providerStatus($payout, $status);

        $left = confirmPayout($payout);

        expect($left->status)->toBe(DisbursementStatus::Processing)->and($left->paid_at)->toBeNull();
    })->with([ProviderPaymentStatus::Pending, ProviderPaymentStatus::Unknown]);

    it('is not marked paid when the answer is for another attempt or another amount, and is logged for a person', function (string $case): void {
        Log::spy();
        $payout = startedPayout();
        match ($case) {
            'another attempt' => providerStatus($payout, ProviderPaymentStatus::Succeeded, $payout->id.'-9'),
            'another amount' => providerStatus($payout, ProviderPaymentStatus::Succeeded, null, '5.00'),
        };

        expect(confirmPayout($payout)->status)->toBe(DisbursementStatus::Processing);
        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $m, array $c) => $c === ['disbursement_id' => $payout->id, 'church_id' => $this->church->id]);
    })->with(['another attempt', 'another amount']);

    it('is left for the next round, and logged without any amount, when the provider will not say', function (): void {
        Log::spy();
        $payout = startedPayout();
        providerStatus($payout, new PaymentRejectedException('no such payout'));

        expect(confirmPayout($payout)->status)->toBe(DisbursementStatus::Processing);
        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $m, array $c) => $c === ['disbursement_id' => $payout->id, 'church_id' => $this->church->id]);
    });

    it('lets the error out when the provider cannot be reached, leaving the payout as it was', function (): void {
        $payout = startedPayout();
        providerStatus($payout, new PaymentProviderUnavailableException('down'));

        expect(fn () => confirmPayout($payout))->toThrow(PaymentProviderUnavailableException::class);
        expect($payout->fresh()->status)->toBe(DisbursementStatus::Processing);
    });

    it('never changes a payout that is paid, and never asks about one the provider has not taken', function (): void {
        $provider = Mockery::mock(DisbursementProvider::class);
        $provider->shouldNotReceive('disbursementStatus');
        app()->instance(DisbursementProvider::class, $provider);
        $paid = startedPayout(['status' => DisbursementStatus::Paid]);
        $pending = Disbursement::factory()->create(['church_id' => $this->church->id]);
        $noReference = startedPayout(['provider_reference' => null]);
        $failed = startedPayout(['status' => DisbursementStatus::Failed]);
        $child = startedPayout(['batch_id' => $paid->id]);

        foreach ([$paid, $pending, $noReference, $failed, $child] as $payout) {
            confirmPayout($payout);
        }

        expect($paid->fresh()->status)->toBe(DisbursementStatus::Paid)->and(app(ConfirmDisbursementAction::class)->execute('01ARZ3NDEKTSV4RRFFQ69G5FAV'))->toBeNull();
    });

    it('pays a bank batch and every gift in it when the provider says the batch succeeded', function (): void {
        $batch = startedPayout(['contribution_id' => null, 'requested_amount' => '105.00']);
        $first = Disbursement::factory()->create(['church_id' => $this->church->id, 'batch_id' => $batch->id, 'status' => DisbursementStatus::Processing, 'requested_amount' => '60.00']);
        $second = Disbursement::factory()->create(['church_id' => $this->church->id, 'batch_id' => $batch->id, 'status' => DisbursementStatus::Processing, 'requested_amount' => '45.00']);
        providerStatus($batch, ProviderPaymentStatus::Succeeded);

        confirmPayout($batch);

        expect($batch->fresh()->status)->toBe(DisbursementStatus::Paid)->and($first->fresh()->status)->toBe(DisbursementStatus::Paid)->and($second->fresh()->status)->toBe(DisbursementStatus::Paid);
    });

    it('is paid only once when two answers arrive together (the row is locked and re-read)', function (): void {
        $payout = startedPayout();
        $stale = Disbursement::query()->find($payout->id);
        $payout->forceFill(['status' => DisbursementStatus::Paid, 'paid_at' => now()->subMinute(), 'net_amount' => '97.43'])->save();
        $repository = Mockery::mock(EloquentDisbursementRepository::class)->makePartial();
        $repository->shouldReceive('find')->andReturn($stale);
        app()->instance(DisbursementRepositoryInterface::class, $repository);
        providerStatus($payout, ProviderPaymentStatus::Failed);

        confirmPayout($payout);

        expect($payout->fresh()->status)->toBe(DisbursementStatus::Paid);
    });
});

describe('the every-minute check', function (): void {
    beforeEach(fn () => Queue::fake());

    it('queues a check, on the payments queue, only for started payouts old enough, in every church, and never for a gift inside a batch', function (): void {
        $due = startedPayout(['updated_at' => now()->subMinutes(2)]);
        $theirs = Disbursement::factory()->create(['church_id' => $this->other->id, 'status' => DisbursementStatus::Processing, 'provider_reference' => 'pay-x', 'updated_at' => now()->subMinutes(5)]);
        $fresh = startedPayout(['updated_at' => now()->subSeconds(10)]);
        $paid = startedPayout(['status' => DisbursementStatus::Paid]);
        $noReference = startedPayout(['provider_reference' => null]);
        $child = startedPayout(['batch_id' => $due->id]);

        $this->artisan('giving:check-started-payouts')->assertSuccessful();

        Queue::assertPushedOn('payments', CheckDisbursementJob::class);
        $queued = Queue::pushed(CheckDisbursementJob::class)->map(fn (CheckDisbursementJob $job): string => $job->disbursementId)->sort()->values()->all();
        expect($queued)->toBe(collect([$due->id, $theirs->id])->sort()->values()->all())
            ->and($queued)->not->toContain($fresh->id)->not->toContain($paid->id)->not->toContain($noReference->id)->not->toContain($child->id);
    });

    it('reports payouts unfinished for too long, and stays quiet otherwise', function (): void {
        Log::spy();
        startedPayout(['updated_at' => now()->subMinutes(30)]);

        $this->artisan('giving:check-started-payouts')->assertSuccessful();
        Log::shouldNotHaveReceived('warning');

        startedPayout(['updated_at' => now()->subMinutes(61)]);
        $this->artisan('giving:check-started-payouts')->assertSuccessful();

        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $m, array $c): bool => $m === 'Church payouts have been unfinished for too long' && $c === ['count' => 1]);
    });

    it('is run every minute', function (): void {
        $event = collect(app(Schedule::class)->events())->first(fn ($event): bool => str_contains((string) $event->command, 'giving:check-started-payouts'));

        expect($event)->not->toBeNull()->and($event->expression)->toBe('* * * * *')->and(CheckStartedPayouts::class)->toBeString();
    });

    it('asks through the job, and logs ids only when the job fails', function (): void {
        $payout = startedPayout();
        providerStatus($payout, ProviderPaymentStatus::Succeeded);
        (new CheckDisbursementJob($payout->id))->handle(app(ConfirmDisbursementAction::class));
        expect($payout->fresh()->status)->toBe(DisbursementStatus::Paid);

        Log::spy();
        (new CheckDisbursementJob($payout->id))->failed(new RuntimeException('boom'));
        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $m, array $c) => $c === ['disbursement_id' => $payout->id, 'error' => RuntimeException::class]);
    });
});
