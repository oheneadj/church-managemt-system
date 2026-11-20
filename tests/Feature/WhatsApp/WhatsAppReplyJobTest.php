<?php

declare(strict_types=1);

use App\Contracts\WhatsAppGateway;
use App\DTOs\WhatsApp\OutboundWhatsApp;
use App\DTOs\WhatsApp\WhatsAppReceipt;
use App\Enums\MessageStatus;
use App\Enums\RecipientType;
use App\Exceptions\WhatsAppRejectedException;
use App\Jobs\SendWhatsAppReplyJob;
use App\Models\Church;
use App\Models\MessageLog;
use App\Repositories\MessageLogRepositoryInterface;
use App\Services\CircuitBreaker;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

// The WhatsApp answer job (agreement sections 15, 18 and 37): it records what happened, waits while the provider is
// paused or its circuit is open, and gives up on a message that can never be sent.

beforeEach(function (): void {
    Cache::flush();
    $this->church = Church::factory()->create();
    $this->message = new OutboundWhatsApp($this->church->id, '+233244123456', 'Welcome!', 'checkin_reply', RecipientType::Member, '01member');
});

/** A gateway that does what the test says. */
function gatewayThat(Closure $send): void
{
    app()->instance(WhatsAppGateway::class, new class($send) implements WhatsAppGateway
    {
        public function __construct(private Closure $send) {}

        public function send(OutboundWhatsApp $message): WhatsAppReceipt
        {
            return ($this->send)($message);
        }
    });
}

it('sends the message and logs it as sent', function (): void {
    gatewayThat(fn () => new WhatsAppReceipt('wamid.OK', ['status' => 200]));

    SendWhatsAppReplyJob::dispatchSync($this->message);

    $log = MessageLog::query()->withoutGlobalScopes()->sole();
    expect($log->status)->toBe(MessageStatus::Sent)->and($log->provider_reference)->toBe('wamid.OK')->and($log->sent_at)->not->toBeNull();
});

it('declares how it is retried and where it runs', function (): void {
    $job = new SendWhatsAppReplyJob($this->message);

    expect($job->tries)->toBe(3)->and($job->timeout)->toBe(30)->and($job->backoff)->toBe([5, 20, 60])
        ->and($job->retryUntil()->getTimestamp())->toBeGreaterThan(now()->addMinutes(29)->getTimestamp());
});

it('logs a failure once the retries are used up', function (): void {
    (new SendWhatsAppReplyJob($this->message))->failed(new RuntimeException('Meta is down'));

    expect(MessageLog::query()->withoutGlobalScopes()->sole()->status)->toBe(MessageStatus::Failed);
});

it('gives up at once on a message Meta refuses for good, and does not count it against the circuit', function (): void {
    gatewayThat(fn () => throw new WhatsAppRejectedException('refused'));
    $circuit = app(CircuitBreaker::class);
    foreach (range(1, 4) as $ignored) {
        $circuit->recordFailure('whatsapp');
    }

    $job = new SendWhatsAppReplyJob($this->message);
    $job->withFakeQueueInteractions();
    $job->handle(app(WhatsAppGateway::class), app(MessageLogRepositoryInterface::class), $circuit);

    $job->assertFailed();
    expect($circuit->allows('whatsapp'))->toBeTrue();
});

it('counts a temporary failure against the circuit and lets the queue retry', function (): void {
    gatewayThat(fn () => throw new RuntimeException('timeout'));
    config(['resilience.circuit_breaker.failure_threshold' => 2]);

    foreach (range(1, 2) as $ignored) {
        expect(fn () => (new SendWhatsAppReplyJob($this->message))->handle(app(WhatsAppGateway::class), app(MessageLogRepositoryInterface::class), app(CircuitBreaker::class)))
            ->toThrow(RuntimeException::class);
    }

    expect(app(CircuitBreaker::class)->allows('whatsapp'))->toBeFalse();
});

describe('pausing', function (): void {
    it('holds the message back while paused, and sends it after resume', function (): void {
        gatewayThat(fn () => new WhatsAppReceipt('wamid.OK'));
        Artisan::call('whatsapp:pause');

        $job = new SendWhatsAppReplyJob($this->message);
        $job->withFakeQueueInteractions();
        $job->handle(app(WhatsAppGateway::class), app(MessageLogRepositoryInterface::class), app(CircuitBreaker::class));

        $job->assertReleased(30);
        expect(MessageLog::query()->withoutGlobalScopes()->count())->toBe(0);

        Artisan::call('whatsapp:resume');
        SendWhatsAppReplyJob::dispatchSync($this->message);

        expect(MessageLog::query()->withoutGlobalScopes()->count())->toBe(1);
    });

    it('does not pause text messages, and the other way round', function (): void {
        Artisan::call('whatsapp:pause');

        expect(app(CircuitBreaker::class)->allows('sms'))->toBeTrue()->and(app(CircuitBreaker::class)->isPaused('whatsapp'))->toBeTrue();

        Artisan::call('sms:pause');
        Artisan::call('whatsapp:resume');

        expect(app(CircuitBreaker::class)->isPaused('sms'))->toBeTrue()->and(app(CircuitBreaker::class)->isPaused('whatsapp'))->toBeFalse();
    });

    it('tells the operator what happened', function (): void {
        $this->artisan('whatsapp:pause')->expectsOutputToContain('Check-ins are still recorded')->assertSuccessful();
        $this->artisan('whatsapp:resume')->expectsOutputToContain('going out again')->assertSuccessful();
    });
});
