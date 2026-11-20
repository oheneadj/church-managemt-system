<?php

declare(strict_types=1);

namespace App\Actions\Messages;

use App\DTOs\Messages\QueuedBroadcast;
use App\DTOs\Messages\SendBroadcastDTO;
use App\Enums\BroadcastStatus;
use App\Enums\MessageChannel;
use App\Enums\QueueName;
use App\Exceptions\InsufficientBroadcastCreditsException;
use App\Exceptions\NoBroadcastRecipientsException;
use App\Jobs\SendBroadcastJob;
use App\Repositories\BroadcastRepositoryInterface;
use App\Services\BroadcastEstimator;
use Illuminate\Support\Facades\DB;

/**
 * Accepts a broadcast (docs/08, Send Broadcast; D-065). It is refused when nobody would be reached or when the church cannot
 * pay for it (QA-08 TC-08-09); otherwise it is recorded and queued, and a worker turns it into one text for each member. The
 * credits are taken text by text just before each is sent (D-051), so a broadcast can never overspend the church. Nothing is
 * sent inside the request (agreement section 3).
 *
 * @throws NoBroadcastRecipientsException
 * @throws InsufficientBroadcastCreditsException
 */
final class SendBroadcastAction
{
    /**
     * Takes the estimator and the broadcast repository.
     */
    public function __construct(
        private readonly BroadcastEstimator $estimator,
        private readonly BroadcastRepositoryInterface $broadcasts,
    ) {}

    /**
     * Records the broadcast and queues it.
     */
    public function execute(SendBroadcastDTO $dto): QueuedBroadcast
    {
        $estimate = $this->estimator->estimate($dto->church, $dto->message, $dto->audience);

        if ($estimate->recipients === 0) {
            throw new NoBroadcastRecipientsException;
        }

        if (! $estimate->enoughCredits()) {
            throw new InsufficientBroadcastCreditsException($estimate->creditsNeeded, $estimate->creditBalance);
        }

        $broadcast = DB::transaction(function () use ($dto, $estimate) {
            $broadcast = $this->broadcasts->create([
                'church_id' => $dto->church->id,
                'sent_by' => $dto->actor->id,
                'channel' => MessageChannel::Sms,
                'message' => $dto->message,
                'filters' => $dto->filter,
                'parts_per_message' => $estimate->partsPerMessage,
                'status' => BroadcastStatus::Queued,
            ]);

            SendBroadcastJob::dispatch($broadcast->id)->onQueue(QueueName::Notifications->value)->afterCommit();

            return $broadcast;
        });

        return new QueuedBroadcast($broadcast, $estimate);
    }
}
