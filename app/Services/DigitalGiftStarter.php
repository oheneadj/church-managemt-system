<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ContributionStatus;
use App\Enums\MomoNetwork;
use App\Enums\PaymentMethod;
use App\Enums\QueueName;
use App\Jobs\StartContributionPaymentJob;
use App\Models\Church;
use App\Models\Contribution;
use App\Repositories\ContributionRepositoryInterface;
use App\Repositories\FundTypeRepositoryInterface;
use App\ValueObjects\Money;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * The rules every mobile money gift starts with, whether a staff member helps someone (D-069 d3) or a person gives on the public
 * page (D-073): the church must be able to be paid, the gift is saved pending with the processing fee on top, and the call to the
 * provider is queued. It never opens a transaction: the Action that calls it does. Nothing is counted, receipted or paid out until
 * the provider confirms.
 */
final class DigitalGiftStarter
{
    /**
     * Takes the repositories, the fee calculator and the phone hasher.
     */
    public function __construct(
        private readonly ContributionRepositoryInterface $contributions,
        private readonly FundTypeRepositoryInterface $fundTypes,
        private readonly GivingFeeCalculator $fees,
        private readonly PhoneFingerprint $fingerprints,
    ) {}

    /**
     * Refuses with this message while the church has no payout account the provider has named, because a gift that cannot be paid
     * out must not be taken. What to say depends on who is asking: a staff member is told what to set up, a giver is not.
     *
     * @throws ValidationException
     */
    public function assertReady(Church $church, string $message): void
    {
        if ($church->payoutStatus() !== 'verified') {
            throw ValidationException::withMessages(['payment_method' => $message]);
        }
    }

    /**
     * Saves the gift as pending, with the processing fee and the total asked for.
     *
     * @param  array{member_id?: ?string, service_id?: ?string, pledge_id?: ?string, recorded_by?: ?string, giver_name?: ?string}  $extra
     */
    public function save(Church $church, string $fundTypeId, Money $amount, string $phone, array $extra = []): Contribution
    {
        return $this->contributions->create([
            'church_id' => $church->id,
            'member_id' => $extra['member_id'] ?? null,
            'service_id' => $extra['service_id'] ?? null,
            'pledge_id' => $extra['pledge_id'] ?? null,
            'fund_type_id' => $fundTypeId,
            'fund_name' => $this->fundTypes->find($fundTypeId)->name ?? throw new LogicException('A gift needs a fund that exists; the request checks this.'),
            'amount' => $amount->amount,
            'processing_fee' => $this->fees->collectionFee($amount)->amount,
            'total_paid' => $this->fees->totalToPay($amount)->amount,
            'payment_method' => PaymentMethod::Momo,
            'status' => ContributionStatus::Pending,
            'is_voided' => false,
            'recorded_by' => $extra['recorded_by'] ?? null,
            'giver_name' => $extra['giver_name'] ?? null,
            'payer_phone_hash' => $this->fingerprints->of($phone),
            'contributed_at' => now(),
        ]);
    }

    /**
     * Queues the call to the provider. The job is encrypted because it carries the phone number.
     */
    public function queue(Contribution $gift, string $phone, MomoNetwork $network): void
    {
        StartContributionPaymentJob::dispatch($gift->id, $phone, $network->value)->onQueue(QueueName::Payments->value);
    }
}
