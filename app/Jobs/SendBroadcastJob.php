<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DTOs\Messages\BroadcastAudience;
use App\DTOs\OutboundSms;
use App\Enums\BroadcastStatus;
use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Enums\RecipientType;
use App\Models\Broadcast;
use App\Models\Member;
use App\Repositories\BroadcastRepositoryInterface;
use App\Repositories\MemberRepositoryInterface;
use App\Repositories\MessageLogRepositoryInterface;
use App\Services\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;
use LogicException;
use Throwable;

/**
 * Turns one broadcast into texts (D-065): for each member the filters and the consent rules allow, as things are now, a
 * `queued` message log line and an SMS job that takes the credits and sends (D-051). The members are read a few at a time and each
 * batch is saved in one transaction, so a large church does not fill the memory. It is safe to run twice or after a crash: a
 * member who already has a line for this broadcast is skipped (and the database allows only one), and a broadcast that is
 * finished or failed is left alone. A failure that survives the retries marks the broadcast failed. Only ids are logged.
 */
class SendBroadcastJob implements ShouldQueue
{
    use Queueable;

    /** How many times a worker tries before the broadcast fails. */
    public int $tries = 3;

    /** The longest one try may take, in seconds. */
    public int $timeout = 300;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    /**
     * Takes the id of the broadcast to send.
     */
    public function __construct(public readonly string $broadcastId) {}

    /**
     * Queues a text for every member who is due one, then marks the broadcast done.
     */
    public function handle(
        BroadcastRepositoryInterface $broadcasts,
        MessageLogRepositoryInterface $logs,
        MemberRepositoryInterface $members,
        TenantContext $tenant,
    ): void {
        $broadcast = $broadcasts->find($this->broadcastId);

        if ($broadcast === null || ! in_array($broadcast->status, [BroadcastStatus::Queued, BroadcastStatus::Sending], true)) {
            return;
        }

        $church = $broadcast->church ?? throw new LogicException('A broadcast always belongs to a church.');

        $tenant->run($church, function () use ($broadcasts, $logs, $members, $broadcast): void {
            $broadcasts->markSending($broadcast);
            $texted = array_flip($logs->recipientIdsForBroadcast($broadcast->id));
            $chunk = (int) config('messages.broadcast_chunk_size');
            $audience = BroadcastAudience::fromFilter($broadcast->filters ?? []);

            foreach ($members->forBroadcast($audience, $chunk)->chunk($chunk) as $batch) {
                $texted += $this->queueBatch($logs, $broadcast, $batch, $texted);
            }

            $broadcasts->markDispatched($broadcast, count($texted));
        });
    }

    /**
     * Records the failure when the retries run out, so a broadcast is never left looking as if it is still going.
     */
    public function failed(?Throwable $exception = null): void
    {
        $broadcast = app(BroadcastRepositoryInterface::class)->find($this->broadcastId);

        if ($broadcast !== null && $broadcast->status !== BroadcastStatus::Dispatched) {
            app(BroadcastRepositoryInterface::class)->markFailed($broadcast);
        }

        Log::error('A broadcast failed.', ['broadcast_id' => $this->broadcastId, 'church_id' => $broadcast?->church_id, 'exception' => $exception === null ? null : $exception::class]);
    }

    /**
     * Makes the log line and queues the SMS job for each member of the batch who has none yet, in one transaction, and returns
     * the members it did (their ids as keys).
     *
     * @param  LazyCollection<int, Member>  $batch
     * @param  array<string, int>  $texted
     * @return array<string, int>
     */
    private function queueBatch(MessageLogRepositoryInterface $logs, Broadcast $broadcast, LazyCollection $batch, array $texted): array
    {
        $done = [];

        DB::transaction(function () use ($logs, $broadcast, $batch, $texted, &$done): void {
            foreach ($batch->reject(fn (Member $member): bool => isset($texted[$member->id])) as $member) {
                $log = $logs->create([
                    'church_id' => $broadcast->church_id,
                    'broadcast_id' => $broadcast->id,
                    'channel' => MessageChannel::Sms,
                    'recipient_type' => RecipientType::Member,
                    'recipient_id' => $member->id,
                    'recipient_phone' => $member->phone,
                    'message_type' => 'broadcast',
                    'content' => $broadcast->message,
                    'status' => MessageStatus::Queued,
                ]);

                SendSmsJob::dispatch(new OutboundSms(
                    churchId: $broadcast->church_id,
                    phone: $member->phone,
                    message: $broadcast->message,
                    messageType: 'broadcast',
                    recipientType: RecipientType::Member,
                    recipientId: $member->id,
                    messageLogId: $log->id,
                    creditCost: $broadcast->parts_per_message,
                ))->onQueue('notifications')->afterCommit();

                $done[$member->id] = 1;
            }
        });

        return $done;
    }
}
