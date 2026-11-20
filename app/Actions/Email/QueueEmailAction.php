<?php

declare(strict_types=1);

namespace App\Actions\Email;

use App\DTOs\OutboundEmail;
use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Jobs\SendEmailJob;
use App\Models\MessageLog;
use App\Repositories\MessageLogRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Sends an email the only way allowed: write a `queued` line in the message log, then queue the send on a worker. Nothing
 * is sent inside the request (agreement section 3), and the log line is what makes the send safe to retry (the job
 * checks it before sending). The send is queued only after the line is committed, so a worker can never find it missing.
 */
final class QueueEmailAction
{
    public function __construct(private readonly MessageLogRepositoryInterface $logs) {}

    /**
     * Records the email as queued and queues it on the queue the caller chose.
     */
    public function execute(OutboundEmail $email): MessageLog
    {
        return DB::transaction(function () use ($email): MessageLog {
            $log = $this->logs->create([
                'church_id' => $email->churchId,
                'channel' => MessageChannel::Email,
                'recipient_type' => $email->recipientType,
                'recipient_id' => $email->recipientId,
                'recipient_email' => $email->email,
                'message_type' => $email->messageType,
                'content' => $email->logContent,
                'status' => MessageStatus::Queued,
            ]);

            SendEmailJob::dispatch($email, $log->id)->onQueue($email->queue->value)->afterCommit();

            return $log;
        });
    }
}
