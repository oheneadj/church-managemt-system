<?php

declare(strict_types=1);

namespace App\Actions\Giving;

use App\DTOs\Giving\PublicGift;
use App\DTOs\Giving\StartPublicGiftDTO;
use App\Enums\ContributionStatus;
use App\Models\Contribution;
use App\Repositories\ContributionRepositoryInterface;
use App\Services\DigitalGiftStarter;
use App\Services\GivingFeeCalculator;
use App\Services\PhoneFingerprint;
use App\Services\PublicGiftLinker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Starts a mobile money gift for anyone who opens the church's giving page (D-073), with no staff member involved. It is turned away
 * if the church cannot be paid yet. A bot that filled in the hidden trap field is answered as if it worked, and nothing is saved or
 * asked of any provider. If the same phone already has a payment waiting for the same fund and amount, that one is returned and
 * nothing new is started, so a person with a poor network never makes a duplicate. Otherwise the gift is saved pending with the
 * processing fee on top, linked to no staff member but to the service being held and to the member whose phone it is (PublicGiftLinker),
 * and the provider call is queued (the request never waits on a provider).
 * No change-history line is written: there is no staff member to credit, and the gift row and the provider's record are the trail.
 */
final class StartPublicGiftAction
{
    /**
     * Takes the starter, the repository, the fee calculator and the phone hasher.
     */
    public function __construct(
        private readonly DigitalGiftStarter $starter,
        private readonly ContributionRepositoryInterface $contributions,
        private readonly GivingFeeCalculator $fees,
        private readonly PhoneFingerprint $fingerprints,
        private readonly PublicGiftLinker $links,
    ) {}

    /**
     * Starts the gift, or returns the one already waiting.
     *
     * @throws ValidationException when the church cannot take gifts yet
     */
    public function execute(StartPublicGiftDTO $dto): PublicGift
    {
        if ($dto->trapFilled) {
            return new PublicGift($this->pretend($dto), false);
        }

        $this->starter->assertReady($dto->church, 'Giving is not available here yet.');

        $waiting = $this->contributions->findWaitingFor(
            $this->fingerprints->of($dto->phone),
            $dto->fundTypeId,
            $dto->amount->amount,
            now()->subMinutes((int) config('giving.reuse_window_minutes')),
        );

        if ($waiting !== null) {
            return new PublicGift($waiting, true);
        }

        $gift = DB::transaction(fn (): Contribution => $this->starter->save($dto->church, $dto->fundTypeId, $dto->amount, $dto->phone, [...$this->links->linksFor($dto->church, $dto->phone), 'giver_name' => $dto->giverName]));

        $this->starter->queue($gift, $dto->phone, $dto->network);

        return new PublicGift($gift, false);
    }

    /**
     * A gift that looks real but is not saved, for a bot, so it learns nothing from being turned away.
     */
    private function pretend(StartPublicGiftDTO $dto): Contribution
    {
        $gift = new Contribution([
            'amount' => $dto->amount->amount,
            'processing_fee' => $this->fees->collectionFee($dto->amount)->amount,
            'total_paid' => $this->fees->totalToPay($dto->amount)->amount,
        ]);
        $gift->id = Str::ulid()->toString();
        $gift->status = ContributionStatus::Pending;

        return $gift;
    }
}
