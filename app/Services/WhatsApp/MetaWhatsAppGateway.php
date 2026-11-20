<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppGateway;
use App\DTOs\WhatsApp\OutboundWhatsApp;
use App\DTOs\WhatsApp\WhatsAppReceipt;
use App\Exceptions\WhatsAppRejectedException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Sends WhatsApp messages through Meta's Cloud API (docs/14, section 3): POST /{phone-number-id}/messages with the
 * access token. Meta has no idempotency key, so a retry after a timeout can in rare cases send a message twice.
 * Built from docs/14 while the Meta account is not yet approved; check it against a real sandbox number first.
 */
final class MetaWhatsAppGateway implements WhatsAppGateway
{
    /**
     * Sends one text message and returns Meta's id for it.
     *
     * @throws WhatsAppRejectedException when Meta refuses the message for good
     * @throws RequestException when the failure is temporary (network, rate limit, Meta down), so the job retries
     */
    public function send(OutboundWhatsApp $message): WhatsAppReceipt
    {
        $response = $this->client()->post('/messages', [
            'messaging_product' => 'whatsapp',
            'to' => ltrim($message->phone, '+'),
            'type' => 'text',
            'text' => ['body' => $message->text],
        ]);

        if ($this->isTemporary($response)) {
            $response->throw();
        }

        if (! $response->successful()) {
            $this->reject($response);
        }

        return new WhatsAppReceipt(reference: $response->json('messages.0.id'), response: ['status' => $response->status()]);
    }

    /**
     * The HTTP client pointed at this phone number's messages, with the token and the time limit already set.
     */
    private function client(): PendingRequest
    {
        $base = rtrim((string) config('services.meta.graph_url'), '/').'/'.config('services.meta.graph_version').'/'.config('services.meta.phone_number_id');

        return Http::baseUrl($base)
            ->withToken((string) config('services.meta.access_token'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.meta.timeout_seconds'));
    }

    /**
     * Whether trying again later could work: the rate limit was hit, or Meta had a problem of its own.
     */
    private function isTemporary(Response $response): bool
    {
        return $response->status() === HttpResponse::HTTP_TOO_MANY_REQUESTS || $response->serverError();
    }

    /**
     * Logs why Meta refused (its error code and trace id only) and stops the job from retrying.
     *
     * @throws WhatsAppRejectedException
     */
    private function reject(Response $response): never
    {
        $code = (string) $response->json('error.code', 'unknown');
        $trace = (string) $response->json('error.fbtrace_id', 'none');

        Log::error('Meta refused a WhatsApp message', ['status' => $response->status(), 'error_code' => $code, 'trace_id' => $trace]);

        throw new WhatsAppRejectedException("Meta refused the message: status {$response->status()}, code {$code}, trace {$trace}.");
    }
}
