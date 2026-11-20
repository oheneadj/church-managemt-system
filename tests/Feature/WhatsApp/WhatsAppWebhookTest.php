<?php

declare(strict_types=1);

use App\Enums\WebhookSource;
use App\Jobs\ProcessWhatsAppWebhookJob;
use App\Models\WebhookLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

// The Meta WhatsApp webhook (docs/08 Webhooks, docs/14 section 3, docs/15 webhooks): proving the sender, keeping the
// raw payload, answering 200 at once and doing the work on a worker.

beforeEach(function (): void {
    config(['services.meta.verify_token' => 'verify-me', 'services.meta.app_secret' => 'app-secret']);
    RateLimiter::clear('webhooks');
});

describe('Meta checking our address', function (): void {
    it('echoes the challenge when the token is right', function (): void {
        $this->get(WHATSAPP_WEBHOOK.'?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=1158201444')
            ->assertOk()->assertSee('1158201444', false)->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    });

    it('refuses a wrong token, a wrong mode and a missing token', function (string $query): void {
        $this->get(WHATSAPP_WEBHOOK.'?'.$query)->assertForbidden();
    })->with([
        'wrong token' => 'hub.mode=subscribe&hub.verify_token=nope&hub.challenge=1',
        'wrong mode' => 'hub.mode=unsubscribe&hub.verify_token=verify-me&hub.challenge=1',
        'no token' => 'hub.mode=subscribe&hub.challenge=1',
    ]);

    it('refuses everything when no token is set, even an empty one', function (): void {
        config(['services.meta.verify_token' => '']);

        $this->get(WHATSAPP_WEBHOOK.'?hub.mode=subscribe&hub.verify_token=&hub.challenge=1')->assertForbidden();
    });
});

describe('receiving a message', function (): void {
    it('keeps the raw payload encrypted, queues the work and answers 200 at once', function (): void {
        Queue::fake();
        $payload = metaPayload();

        metaPost($payload)->assertOk()->assertExactJson(['status' => 'ok']);

        $log = WebhookLog::query()->sole();
        expect($log->source)->toBe(WebhookSource::MetaWhatsapp)
            ->and($log->event)->toBe('message')
            ->and($log->processed)->toBeFalse()
            ->and(json_decode($log->payload, true))->toBe($payload)
            ->and(DB::table('webhook_logs')->value('payload'))->not->toContain('233244123456');
        Queue::assertPushedOn('notifications', ProcessWhatsAppWebhookJob::class, fn (ProcessWhatsAppWebhookJob $job): bool => $job->webhookLogId === $log->id);
    });

    it('logs a delivery receipt as a status and still queues it', function (): void {
        Queue::fake();
        $status = ['entry' => [['changes' => [['value' => ['statuses' => [['id' => 'wamid.X', 'status' => 'delivered']]]]]]]];

        metaPost($status)->assertOk();

        expect(WebhookLog::query()->sole()->event)->toBe('status');
    });

    it('answers 200 to a body that is not JSON, logs why, and queues nothing', function (): void {
        Queue::fake();

        metaPost('this is not json')->assertOk();

        expect(WebhookLog::query()->sole()->error)->toBe('The body was not valid JSON.');
        Queue::assertNothingPushed();
    });
});

describe('proving the sender', function (): void {
    it('refuses a missing, wrong or forged signature and keeps nothing', function (?string $signature): void {
        Queue::fake();
        $body = json_encode(metaPayload());

        test()->call('POST', WHATSAPP_WEBHOOK, [], [], [], array_filter(['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => $signature]), $body)->assertForbidden();

        expect(WebhookLog::query()->count())->toBe(0);
        Queue::assertNothingPushed();
    })->with([
        'no signature' => [null],
        'wrong signature' => ['sha256='.str_repeat('0', 64)],
        'signed with another secret' => [fn () => 'sha256='.hash_hmac('sha256', json_encode(metaPayload()), 'other-secret')],
        'no prefix' => [fn () => hash_hmac('sha256', json_encode(metaPayload()), 'app-secret')],
    ]);

    it('refuses a body changed after it was signed', function (): void {
        $signature = 'sha256='.hash_hmac('sha256', json_encode(metaPayload('CHECKIN')), 'app-secret');

        metaPost(metaPayload('CHECKIN GRA7F'), $signature)->assertForbidden();
    });

    it('refuses everything when no app secret is set', function (): void {
        config(['services.meta.app_secret' => '']);
        $body = json_encode(metaPayload());

        metaPost($body, 'sha256='.hash_hmac('sha256', $body, ''))->assertForbidden();
    });
});

it('is limited per address', function (): void {
    config(['limits.webhooks_per_minute' => 2]);
    Queue::fake();

    metaPost(metaPayload())->assertOk();
    metaPost(metaPayload())->assertOk();
    metaPost(metaPayload())->assertStatus(429);
});

it('is not reachable on a church address', function (): void {
    $this->postJson('http://grace.localhost/api/v1/webhooks/whatsapp', [])->assertNotFound();
});
