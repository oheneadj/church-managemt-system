<?php

declare(strict_types=1);

use App\Contracts\WhatsAppGateway;
use App\DTOs\WhatsApp\OutboundWhatsApp;
use App\Enums\RecipientType;
use App\Exceptions\WhatsAppRejectedException;
use App\Exceptions\WhatsAppUnavailableException;
use App\Services\WhatsApp\LogWhatsAppGateway;
use App\Services\WhatsApp\MetaWhatsAppGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Sending through Meta's Cloud API (docs/14 section 3). Every call is faked; no real message is ever sent.

const META_URL = 'https://graph.facebook.com/v18.0/1234567890/messages';

beforeEach(function (): void {
    config(['services.whatsapp.driver' => 'meta', 'services.meta.access_token' => 'meta-token', 'services.meta.phone_number_id' => '1234567890']);
    $this->message = new OutboundWhatsApp('01church', '+233244123456', 'Welcome, Emmanuel!', 'checkin_reply', RecipientType::Member, '01member');
});

it('posts the text to the phone number\'s messages with the token, and returns Meta\'s id', function (): void {
    Http::fake([META_URL => Http::response(['messages' => [['id' => 'wamid.REPLY']]], 200)]);

    $receipt = (new MetaWhatsAppGateway)->send($this->message);

    expect($receipt->reference)->toBe('wamid.REPLY');
    Http::assertSent(fn (Request $request): bool => $request->url() === META_URL
        && $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer meta-token')
        && $request->data() === ['messaging_product' => 'whatsapp', 'to' => '233244123456', 'type' => 'text', 'text' => ['body' => 'Welcome, Emmanuel!']]);
});

it('gives up at once when Meta refuses for good, and logs only the codes', function (int $status): void {
    Http::fake([META_URL => Http::response(['error' => ['code' => 131047, 'message' => 'Re-engagement message', 'fbtrace_id' => 'trace-1']], $status)]);
    Log::spy();

    expect(fn () => (new MetaWhatsAppGateway)->send($this->message))->toThrow(WhatsAppRejectedException::class, 'code 131047, trace trace-1');

    Log::shouldHaveReceived('error')->withArgs(fn (string $text, array $context): bool => ! str_contains(json_encode($context), '233244123456') && ! str_contains(json_encode($context), 'Welcome'));
})->with([400, 401, 403]);

it('lets the job retry when Meta is busy or down', function (int $status): void {
    Http::fake([META_URL => Http::response(['error' => ['code' => 80007]], $status)]);

    expect(fn () => (new MetaWhatsAppGateway)->send($this->message))->toThrow(RequestException::class);
})->with([429, 500, 503]);

describe('choosing the driver', function (): void {
    it('uses Meta when it is set up', function (): void {
        expect(app(WhatsAppGateway::class))->toBeInstanceOf(MetaWhatsAppGateway::class);
    });

    it('refuses Meta without a token or phone number id', function (string $missing): void {
        config(["services.meta.{$missing}" => '']);

        expect(fn () => app(WhatsAppGateway::class))->toThrow(WhatsAppUnavailableException::class, 'META_ACCESS_TOKEN and META_PHONE_NUMBER_ID');
    })->with(['access_token', 'phone_number_id']);

    it('uses the log driver in development and refuses it in production', function (): void {
        config(['services.whatsapp.driver' => 'log']);
        expect(app(WhatsAppGateway::class))->toBeInstanceOf(LogWhatsAppGateway::class);

        app()->detectEnvironment(fn () => 'production');
        expect(fn () => app(WhatsAppGateway::class))->toThrow(WhatsAppUnavailableException::class, 'cannot be used in production');
    });

    it('refuses a driver that does not exist', function (): void {
        config(['services.whatsapp.driver' => 'carrier-pigeon']);

        expect(fn () => app(WhatsAppGateway::class))->toThrow(WhatsAppUnavailableException::class, 'carrier-pigeon');
    });
});

describe('the log driver', function (): void {
    it('writes the text only in the local environment', function (): void {
        Log::spy();
        $gateway = new LogWhatsAppGateway;

        app()->detectEnvironment(fn () => 'local');
        $gateway->send($this->message);
        Log::shouldHaveReceived('info')->withArgs(fn (string $text, array $context = []): bool => ($context['message'] ?? null) === 'Welcome, Emmanuel!')->once();

        app()->detectEnvironment(fn () => 'staging');
        $gateway->send($this->message);
        Log::shouldHaveReceived('info')->withArgs(fn (string $text, array $context = []): bool => $context === [])->once();
    });
});
