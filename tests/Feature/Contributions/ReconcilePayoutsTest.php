<?php

declare(strict_types=1);

use App\Actions\Contributions\ReconcilePayoutsAction;
use App\Console\Commands\ReconcilePayouts;
use App\Enums\DisbursementStatus;
use App\Enums\MomoNetwork;
use App\Enums\PaymentMethod;
use App\Enums\PayoutMethod;
use App\Models\Church;
use App\Models\Contribution;
use App\Models\Disbursement;
use App\Models\FundType;
use App\Services\PayoutReconciler;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;

// The daily check that what was collected has been paid out, to the pesewa (D-018, D-069 step e5): a completed gift with no payout, a payout
// stuck for a day, a bank church left unbatched, figures that do not add up, and a paid batch with an unpaid gift in it. It finds what should
// never happen and logs ids only. Money code, so every finding is tested both ways: found when it is wrong, quiet when it is right.

/** A completed mobile money gift, a while ago. */
function oldGift(string $amount = '100.00', array $overrides = []): Contribution
{
    return Contribution::factory()->create([
        'church_id' => test()->church->id, 'fund_type_id' => test()->fund->id, 'amount' => $amount, 'payment_method' => PaymentMethod::Momo, 'recorded_by' => null,
        'updated_at' => now()->subHour(), ...$overrides,
    ]);
}

/** A payout that adds up, for a gift: the gross is the gift, less a platform fee of 1.00. */
function payoutFor(Contribution $gift, array $overrides = []): Disbursement
{
    return Disbursement::factory()->create([
        'church_id' => $gift->church_id, 'contribution_id' => $gift->id, 'gross_amount' => $gift->amount, 'platform_fee' => '1.00',
        'requested_amount' => bcsub($gift->amount, '1.00', 2), 'status' => DisbursementStatus::Pending, 'updated_at' => now(), ...$overrides,
    ]);
}

/** What the reconciler finds. */
function found(): array
{
    return app(PayoutReconciler::class)->findings();
}

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-11-12 12:00:00');
    $this->church = Church::factory()->create(['slug' => 'grace']);
    $this->other = Church::factory()->create(['slug' => 'other']);
    $this->fund = FundType::factory()->create(['church_id' => $this->church->id]);
});

afterEach(fn () => CarbonImmutable::setTestNow());

describe('when all is well', function (): void {
    it('finds nothing, and the log says the check ran', function (): void {
        Log::spy();
        $gift = oldGift();
        payoutFor($gift);
        payoutFor(oldGift('50.00'), ['status' => DisbursementStatus::Paid, 'provider_fee' => '1.57', 'net_amount' => '47.43', 'requested_amount' => '49.00']);

        expect(app(ReconcilePayoutsAction::class)->execute())->toBe([]);

        Log::shouldHaveReceived('info')->once()->with('Payout reconciliation found nothing wrong');
        Log::shouldNotHaveReceived('critical');
    });
});

describe('a completed gift with no payout', function (): void {
    it('is found once it is older than the grace, and not before', function (): void {
        $old = oldGift('100.00', ['updated_at' => now()->subMinutes(11)]);
        oldGift('100.00', ['updated_at' => now()->subMinutes(5)]);

        expect(found()['missing_payout'])->toBe([$old->id]);
    });

    it('is not found for a gift that is pending, failed, cash, or has a payout', function (): void {
        Contribution::factory()->pending()->create(['church_id' => $this->church->id, 'fund_type_id' => $this->fund->id, 'updated_at' => now()->subHour()]);
        Contribution::factory()->failed()->create(['church_id' => $this->church->id, 'fund_type_id' => $this->fund->id, 'updated_at' => now()->subHour()]);
        Contribution::factory()->create(['church_id' => $this->church->id, 'fund_type_id' => $this->fund->id, 'updated_at' => now()->subHour()]);
        payoutFor(oldGift());

        expect(found())->toBe([]);
    });

    it('is found in any church', function (): void {
        $theirFund = FundType::factory()->create(['church_id' => $this->other->id]);
        $theirs = Contribution::factory()->create(['church_id' => $this->other->id, 'fund_type_id' => $theirFund->id, 'payment_method' => PaymentMethod::Momo, 'updated_at' => now()->subHour()]);

        expect(found()['missing_payout'])->toBe([$theirs->id]);
    });
});

describe('a payout stuck for a day', function (): void {
    it('is found when pending, processing or failed for more than 24 hours, and not before, nor when paid', function (DisbursementStatus $status): void {
        $stuck = payoutFor(oldGift(), ['status' => $status, 'updated_at' => now()->subHours(25)]);
        payoutFor(oldGift(), ['status' => $status, 'updated_at' => now()->subHours(23)]);

        expect(found()['stuck_payout'])->toBe([$stuck->id]);
    })->with([DisbursementStatus::Pending, DisbursementStatus::Processing, DisbursementStatus::Failed]);

    it('names the batch, and not the gifts inside it, when a batch is stuck (the batch is what matters)', function (): void {
        $batch = Disbursement::factory()->create(['church_id' => $this->church->id, 'contribution_id' => null, 'gross_amount' => '101.00', 'platform_fee' => '1.00', 'requested_amount' => '100.00', 'status' => DisbursementStatus::Processing, 'updated_at' => now()->subDays(3)]);
        payoutFor(oldGift('101.00'), ['batch_id' => $batch->id, 'status' => DisbursementStatus::Processing, 'requested_amount' => '100.00', 'updated_at' => now()->subDays(3)]);

        expect(found()['stuck_payout'])->toBe([$batch->id]);
    });

    it('is not found for a paid payout, or a gift inside a batch (the batch is what matters)', function (): void {
        payoutFor(oldGift(), ['status' => DisbursementStatus::Paid, 'updated_at' => now()->subDays(3)]);
        $batch = Disbursement::factory()->create(['church_id' => $this->church->id, 'contribution_id' => null, 'gross_amount' => '50.50', 'platform_fee' => '0.50', 'requested_amount' => '50.00', 'status' => DisbursementStatus::Paid, 'updated_at' => now()->subDays(3)]);
        payoutFor(oldGift('50.50'), ['batch_id' => $batch->id, 'status' => DisbursementStatus::Paid, 'gross_amount' => '50.50', 'platform_fee' => '0.50', 'requested_amount' => '50.00', 'updated_at' => now()->subDays(3)]);

        expect(found())->toBe([]);
    });
});

describe('a bank church left unbatched', function (): void {
    beforeEach(function (): void {
        $this->church->update(['payout_method' => PayoutMethod::Bank, 'payout_network' => null, 'payout_bank_code' => '058', 'payout_account_number' => '0123456789', 'payout_verified_at' => now()]);
    });

    it('is found when its waiting payouts have been over GHS 100 for more than a day', function (): void {
        payoutFor(oldGift('70.00'), ['updated_at' => now()->subHours(25)]);
        payoutFor(oldGift('50.00'), ['updated_at' => now()->subHours(25)]);

        expect(found()['unbatched_bank_payouts'])->toBe([$this->church->id]);
    });

    it('is not found while the total is under the minimum, only just over a day, or for a mobile money church', function (): void {
        payoutFor(oldGift('70.00'), ['updated_at' => now()->subHours(25)]);
        expect(found()['unbatched_bank_payouts'] ?? [])->toBe([]);

        $this->church->update(['payout_method' => PayoutMethod::Momo, 'payout_network' => MomoNetwork::Mtn, 'payout_bank_code' => null, 'payout_account_number' => '+233244000111']);
        payoutFor(oldGift('500.00'), ['updated_at' => now()->subHours(25)]);
        expect(found()['unbatched_bank_payouts'] ?? [])->toBe([]);
    });
});

describe('figures that do not add up', function (): void {
    it('finds a payout whose requested amount is not the gross less the platform fee', function (): void {
        $gift = oldGift('100.00');
        $wrong = payoutFor($gift, ['requested_amount' => '98.50']);

        expect(found()['wrong_figures'])->toBe([$wrong->id]);
    });

    it('finds a paid payout whose net is not the requested less the provider fee', function (): void {
        $paid = payoutFor(oldGift('100.00'), ['status' => DisbursementStatus::Paid, 'provider_fee' => '1.57', 'net_amount' => '97.00']);

        expect(found()['wrong_figures'])->toBe([$paid->id]);
    });

    it('finds a payout whose gross is not the gift it pays', function (): void {
        $gift = oldGift('100.00');
        $wrong = payoutFor($gift, ['gross_amount' => '90.00', 'requested_amount' => '89.00']);

        expect(found()['wrong_figures'])->toBe([$wrong->id]);
    });

    it('finds a batch that is not the total of the gifts inside it', function (): void {
        $batch = Disbursement::factory()->create(['church_id' => $this->church->id, 'contribution_id' => null, 'gross_amount' => '120.00', 'platform_fee' => '1.00', 'requested_amount' => '119.00', 'status' => DisbursementStatus::Processing, 'updated_at' => now()]);
        payoutFor(oldGift('60.00'), ['batch_id' => $batch->id, 'status' => DisbursementStatus::Processing]);
        payoutFor(oldGift('50.00'), ['batch_id' => $batch->id, 'status' => DisbursementStatus::Processing]);

        expect(found()['wrong_figures'])->toBe([$batch->id]);
    });

    it('does not find a payout that adds up, a paid one without a fee yet, a batch that equals its gifts, or one changed over a fortnight ago', function (): void {
        payoutFor(oldGift('100.00'), ['status' => DisbursementStatus::Paid]);
        $batch = Disbursement::factory()->create(['church_id' => $this->church->id, 'contribution_id' => null, 'gross_amount' => '111.00', 'platform_fee' => '2.00', 'requested_amount' => '109.00', 'status' => DisbursementStatus::Processing, 'updated_at' => now()]);
        payoutFor(oldGift('60.00'), ['batch_id' => $batch->id, 'status' => DisbursementStatus::Processing, 'requested_amount' => '59.00']);
        payoutFor(oldGift('51.00'), ['batch_id' => $batch->id, 'status' => DisbursementStatus::Processing, 'requested_amount' => '50.00']);
        payoutFor(oldGift('100.00'), ['requested_amount' => '1.00', 'status' => DisbursementStatus::Paid, 'updated_at' => now()->subDays(15)]);

        expect(found())->toBe([]);
    });
});

describe('a paid batch with a gift not paid', function (): void {
    it('is found, and not when every gift in it is paid', function (): void {
        $good = Disbursement::factory()->create(['church_id' => $this->church->id, 'contribution_id' => null, 'gross_amount' => '51.00', 'platform_fee' => '1.00', 'requested_amount' => '50.00', 'status' => DisbursementStatus::Paid, 'updated_at' => now()]);
        payoutFor(oldGift('51.00'), ['batch_id' => $good->id, 'status' => DisbursementStatus::Paid, 'requested_amount' => '50.00']);
        $bad = Disbursement::factory()->create(['church_id' => $this->church->id, 'contribution_id' => null, 'gross_amount' => '51.00', 'platform_fee' => '1.00', 'requested_amount' => '50.00', 'status' => DisbursementStatus::Paid, 'updated_at' => now()]);
        payoutFor(oldGift('51.00'), ['batch_id' => $bad->id, 'status' => DisbursementStatus::Processing, 'requested_amount' => '50.00']);

        expect(found()['batch_inconsistent'])->toBe([$bad->id]);
    });
});

describe('what is reported', function (): void {
    it('logs a critical line with the counts and the first ids only, never an amount or a name', function (): void {
        Log::spy();
        config(['giving.reconcile_ids_logged' => 2]);
        $ids = [];
        foreach (range(1, 3) as $ignored) {
            $ids[] = oldGift('123.45', ['updated_at' => now()->subHour()])->id;
        }

        $findings = app(ReconcilePayoutsAction::class)->execute();

        expect($findings['missing_payout'])->toHaveCount(3);
        Log::shouldHaveReceived('critical')->once()->withArgs(function (string $message, array $context): bool {
            return $message === 'Payout reconciliation found problems' && $context['counts'] === ['missing_payout' => 3]
                && count($context['ids']['missing_payout']) === 2 && ! str_contains(json_encode($context), '123.45');
        });
    });

    it('ends the command with a failure when something is wrong, and with success when not', function (): void {
        $this->artisan('giving:reconcile-payouts')->assertSuccessful()->expectsOutputToContain('All payouts reconcile.');

        oldGift();

        $this->artisan('giving:reconcile-payouts')->assertFailed()->expectsOutputToContain('missing_payout: 1');
    });

    it('is run once a day, at 02:30 UTC', function (): void {
        $event = collect(app(Schedule::class)->events())->first(fn ($event): bool => str_contains((string) $event->command, 'giving:reconcile-payouts'));

        expect($event)->not->toBeNull()->and($event->expression)->toBe('30 2 * * *')->and(ReconcilePayouts::class)->toBeString();
    });
});
