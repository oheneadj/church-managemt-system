<?php

declare(strict_types=1);

namespace App\Actions\Contributions;

use App\DTOs\OutboundSms;
use App\Enums\MemberStatus;
use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Enums\PaymentMethod;
use App\Enums\RecipientType;
use App\Jobs\SendSmsJob;
use App\Models\Church;
use App\Models\Contribution;
use App\Models\Member;
use App\Repositories\ContributionRepositoryInterface;
use App\Repositories\MessageLogRepositoryInterface;
use App\Services\ContributionReceiptMessage;
use App\Services\SmsSegmenter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * Queues the receipt text for a recorded gift, when it should get one (D-051): the gift stands (not voided), cash gifts
 * only when the church has not switched cash receipts off, and the member exists, is alive, has a phone number and has
 * not turned SMS off. A church with too few credits gets no text and a warning in the log, never an error for the person
 * recording the gift. The text is paid for later, by the SMS job. The log row and the claim that makes it one receipt per
 * gift are saved together. It runs as the gift's church (the listener sets that).
 */
final class SendContributionReceiptAction
{
    /** The kind of text this is, as it is named in the message log. */
    public const MESSAGE_TYPE = 'contribution_receipt';

    /**
     * Takes the repositories and the services that write and count the text.
     */
    public function __construct(
        private readonly ContributionRepositoryInterface $contributions,
        private readonly MessageLogRepositoryInterface $logs,
        private readonly ContributionReceiptMessage $message,
        private readonly SmsSegmenter $segmenter,
    ) {}

    /**
     * Queues the receipt if the gift should get one. Does nothing, quietly, if it should not.
     */
    public function execute(Contribution $contribution): void
    {
        $contribution->loadMissing(['member', 'church']);
        $church = $contribution->church ?? throw new LogicException('A gift always belongs to a church.');
        $member = $contribution->member;

        if (! $this->shouldReceive($church, $contribution, $member) || $member === null) {
            return;
        }

        $text = $this->message->write($church, $contribution, $this->monthTotal($church, $contribution, $member));
        $credits = $this->segmenter->parts($text);

        if ($church->sms_credits < $credits) {
            Log::warning('SMS send blocked: insufficient credits', ['church_id' => $church->id, 'contribution_id' => $contribution->id]);

            return;
        }

        $this->queue($church, $contribution, $member, $text, $credits);
    }

    /**
     * Whether this gift gets a receipt at all.
     */
    private function shouldReceive(Church $church, Contribution $contribution, ?Member $member): bool
    {
        if ($contribution->is_voided || ($contribution->payment_method === PaymentMethod::Cash && ! $church->sendsCashReceipts())) {
            return false;
        }

        return $member !== null && $member->status !== MemberStatus::Deceased && $member->phone !== '' && $member->sms_opted_in;
    }

    /**
     * The member's giving in the month of the gift (the church's own month), this gift included.
     *
     * @return numeric-string
     */
    private function monthTotal(Church $church, Contribution $contribution, Member $member): string
    {
        $given = CarbonImmutable::instance($contribution->contributed_at)->setTimezone($church->timezone);

        return $this->contributions->totalForMemberBetween($member->id, $given->startOfMonth()->utc(), $given->endOfMonth()->utc());
    }

    /**
     * Claims the gift's receipt and queues the text with its message log row, in one transaction. A second caller finds the
     * claim taken and queues nothing.
     */
    private function queue(Church $church, Contribution $contribution, Member $member, string $text, int $credits): void
    {
        DB::transaction(function () use ($church, $contribution, $member, $text, $credits): void {
            if (! $this->contributions->claimReceipt($contribution)) {
                return;
            }

            $log = $this->logs->create([
                'church_id' => $church->id,
                'channel' => MessageChannel::Sms,
                'recipient_type' => RecipientType::Member,
                'recipient_id' => $member->id,
                'recipient_phone' => $member->phone,
                'message_type' => self::MESSAGE_TYPE,
                'content' => $text,
                'status' => MessageStatus::Queued,
            ]);

            SendSmsJob::dispatch(new OutboundSms(
                churchId: $church->id,
                phone: $member->phone,
                message: $text,
                messageType: self::MESSAGE_TYPE,
                recipientType: RecipientType::Member,
                recipientId: $member->id,
                messageLogId: $log->id,
                creditCost: $credits,
            ))->onQueue('notifications')->afterCommit();
        });
    }
}
