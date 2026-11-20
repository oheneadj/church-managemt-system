<?php

declare(strict_types=1);

use App\Actions\Contributions\SettleContributionAction;
use App\DTOs\Contributions\SettleContributionDTO;
use App\DTOs\Payments\CollectionResult;
use App\Enums\ContributionFailureReason;
use App\Enums\ContributionStatus;
use App\Enums\DisbursementStatus;
use App\Enums\PaymentProviderName;
use App\Enums\PayoutMethod;
use App\Enums\ProviderPaymentStatus;
use App\Models\Church;
use App\Models\Contribution;
use App\Models\Disbursement;
use App\Models\FundType;
use App\Repositories\DisbursementRepositoryInterface;

// The payout a church is owed for each completed digital gift (D-018, D-072, docs/04 Platform Fee, QA-15): one record for each gift, the
// platform's fee kept on it, the church owed the whole gift less only that fee. Money code, so the figures are checked exactly.

/** Settles a pending gift as paid. */
function payGift(Contribution $gift): void
{
    app(SettleContributionAction::class)->execute(new SettleContributionDTO($gift->id, new CollectionResult('ppz-'.$gift->id, $gift->id, ProviderPaymentStatus::Succeeded, (string) $gift->total_paid, null, null)));
}

/** A pending digital gift of an amount, with the collection fee on top. */
function pendingGift(string $amount, string $total): Contribution
{
    return Contribution::factory()->pending()->create([
        'church_id' => test()->church->id, 'fund_type_id' => test()->fund->id, 'amount' => $amount, 'total_paid' => $total, 'recorded_by' => null,
    ]);
}

beforeEach(function (): void {
    $this->church = Church::factory()->create(['slug' => 'grace']);
    $this->fund = FundType::factory()->create(['church_id' => $this->church->id]);
});

describe('what the church is owed', function (): void {
    it('is the whole gift less the platform fee, recorded when the gift completes', function (string $amount, string $total, string $fee, string $requested): void {
        $gift = pendingGift($amount, $total);

        payGift($gift);

        $owed = Disbursement::query()->sole();
        expect($owed->contribution_id)->toBe($gift->id)->and($owed->church_id)->toBe($this->church->id)->and($owed->gross_amount)->toBe($amount)
            ->and($owed->platform_fee)->toBe($fee)->and($owed->requested_amount)->toBe($requested)
            ->and($owed->status)->toBe(DisbursementStatus::Pending)->and($owed->provider)->toBe(PaymentProviderName::Payperlez)
            ->and($owed->attempts)->toBe(0)->and($owed->provider_fee)->toBeNull()->and($owed->net_amount)->toBeNull()->and($owed->paid_at)->toBeNull();
    })->with([
        'the floor, GHS 1' => ['1.00', '1.02', '0.10', '0.90'],
        'GHS 100' => ['100.00', '101.99', '1.00', '99.00'],
        'a tie that rounds up' => ['10.50', '10.71', '0.11', '10.39'],
        'the cap, GHS 5,000' => ['5000.00', '5099.50', '10.00', '4990.00'],
        'the largest gift' => ['30000.00', '30597.00', '10.00', '29990.00'],
    ]);

    it('is one record for each gift however many times the gift is settled', function (): void {
        $gift = pendingGift('100.00', '101.99');

        payGift($gift);
        payGift($gift);
        app(DisbursementRepositoryInterface::class)->createForGift(['church_id' => $this->church->id, 'contribution_id' => $gift->id, 'gross_amount' => '1.00', 'platform_fee' => '0.10', 'requested_amount' => '0.90', 'status' => DisbursementStatus::Pending, 'provider' => PaymentProviderName::Payperlez]);

        expect(Disbursement::query()->count())->toBe(1)->and(Disbursement::query()->sole()->gross_amount)->toBe('100.00');
    });

    it('keeps the fee that was charged, so a later change to the rate never alters it', function (): void {
        payGift(pendingGift('100.00', '101.99'));

        config(['giving.platform_fee_rate' => '0.05']);
        payGift(pendingGift('100.00', '101.99'));

        expect(Disbursement::query()->orderBy('created_at')->pluck('platform_fee')->all())->toBe(['1.00', '5.00']);
    });

    it('is owed the same for a church that settles to a bank; the bank minimum only delays the sending (D-072)', function (): void {
        $this->church->update(['payout_method' => PayoutMethod::Bank, 'payout_bank_code' => '058', 'payout_account_number' => '0123456789']);

        payGift(pendingGift('20.00', '20.40'));

        expect(Disbursement::query()->sole()->requested_amount)->toBe('19.80');
    });

    it('is owed for a late approval too, and only once', function (): void {
        $gift = pendingGift('50.00', '51.00');
        $gift->update(['status' => ContributionStatus::Failed, 'failure_reason' => ContributionFailureReason::Expired]);

        payGift($gift);
        payGift($gift);

        expect(Disbursement::query()->count())->toBe(1);
    });
});

describe('when nothing is owed', function (): void {
    it('has no record for a gift that is pending, failed, or cash', function (): void {
        $pending = pendingGift('100.00', '101.99');
        $failed = pendingGift('100.00', '101.99');
        app(SettleContributionAction::class)->execute(new SettleContributionDTO($failed->id, new CollectionResult('', $failed->id, ProviderPaymentStatus::Failed, '101.99', null, null)));
        app(SettleContributionAction::class)->execute(new SettleContributionDTO($pending->id, new CollectionResult('', $pending->id, ProviderPaymentStatus::Pending, '101.99', null, null)));
        Contribution::factory()->create(['church_id' => $this->church->id, 'fund_type_id' => $this->fund->id]);

        expect(Disbursement::query()->count())->toBe(0);
    });

    it('has no record when the provider\'s answer does not match the gift', function (): void {
        $gift = pendingGift('100.00', '101.99');

        app(SettleContributionAction::class)->execute(new SettleContributionDTO($gift->id, new CollectionResult('ppz-1', $gift->id, ProviderPaymentStatus::Succeeded, '5.00', null, null)));

        expect(Disbursement::query()->count())->toBe(0);
    });
});
