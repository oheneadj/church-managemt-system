<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\WhatsAppGateway;
use App\DTOs\WhatsApp\OutboundWhatsApp;
use App\DTOs\WhatsApp\WhatsAppReceipt;
use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Enums\RecipientType;
use App\Exceptions\WhatsAppRejectedException;
use App\Repositories\MessageLogRepositoryInterface;
use App\Services\CircuitBreaker;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Sends one WhatsApp answer through the configured gateway and records the outcome in message_logs. Like the SMS job,
 * it waits while the provider's circuit is open or sending is paused (`whatsapp:pause`), and gives up on a message the
 * provider refuses for good. A number that is on no church's list is logged without the number, so a stranger's
 * phone is not kept.
 */
class SendWhatsAppReplyJob implements ShouldQueue
{
    use Queueable;

    /** Real failures of one message that fail it for good. Messages held back by an open circuit do not count. */
    public int $tries = 3;

    public int $maxExceptions = 3;

    public int $timeout = 30;

    /** @var list<int> */
    public array $backoff = [5, 20, 60];

    /**
     * Takes the message to send.
     */
    public function __construct(public readonly OutboundWhatsApp $message) {}

    /**
     * Keeps trying for the retry window while the circuit is open or the provider is failing.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(config('whatsapp.retry_minutes'));
    }

    /**
     * Sends the message and records it as sent. While the circuit is open or sending is paused, it waits.
     */
    public function handle(WhatsAppGateway $gateway, MessageLogRepositoryInterface $logs, CircuitBreaker $circuit): void
    {
        $name = config('whatsapp.circuit');

        if (! $circuit->allows($name)) {
            $this->release(config('whatsapp.release_seconds'));

            return;
        }

        try {
            $receipt = $gateway->send($this->message);
        } catch (WhatsAppRejectedException $rejected) {
            // The provider answered, so it is up: this message is the problem, not the provider.
            $circuit->recordSuccess($name);
            $this->fail($rejected);

            return;
        } catch (Throwable $temporary) {
            $circuit->recordFailure($name);

            throw $temporary;
        }

        $circuit->recordSuccess($name);
        $this->record($logs, MessageStatus::Sent, $receipt);
    }

    /**
     * Records the message as failed once every retry is used up.
     */
    public function failed(Throwable $exception): void
    {
        $this->record(app(MessageLogRepositoryInterface::class), MessageStatus::Failed);
    }

    /**
     * Writes one line to the message log with the given outcome.
     */
    private function record(MessageLogRepositoryInterface $logs, MessageStatus $status, ?WhatsAppReceipt $receipt = null): void
    {
        $isMember = $this->message->recipientType === RecipientType::Member;

        $logs->create([
            'church_id' => $this->message->churchId,
            'channel' => MessageChannel::Whatsapp,
            'recipient_type' => $this->message->recipientType,
            'recipient_id' => $this->message->recipientId,
            'recipient_phone' => $isMember ? $this->message->phone : null,
            'message_type' => $this->message->messageType,
            'content' => $this->message->text,
            'status' => $status,
            'provider_reference' => $receipt?->reference,
            'provider_response' => $receipt?->response,
            'sent_at' => $status === MessageStatus::Sent ? now() : null,
        ]);
    }
}
