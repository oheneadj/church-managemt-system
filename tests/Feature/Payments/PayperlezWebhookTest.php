<?php

declare(strict_types=1);

use App\Enums\WebhookSource;
use App\Models\WebhookLog;
use App\Repositories\WebhookLogRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;

// The PayPerlez webhook (docs/08 Webhooks, docs/14 section 7, docs/15 webhooks): proving the sender and the time, keeping the raw
// payload encrypted, dropping a repeated event, and answering 200 at once. It is on the platform host and has no church, so
// there is no tenant isolation to test; the signature is its only guard.

const PAYPERLEZ_WEBHOOK = 'http://localhost/api/v1/webhooks/payperlez';

/** A signed post, as PayPerlez would send it. `$time` and `$secret` let a test sign it wrongly. */
function payperlezPost(array|string $payload, ?int $time = null, string $secret = 'whsec_test', ?string $header = null): TestResponse
{
    $body = is_array($payload) ? json_encode($payload) : $payload;
    $time ??= time();
    $header ??= 't='.$time.',v1='.hash_hmac('sha256', $time.'.'.$body, $secret);

    return test()->call('POST', PAYPERLEZ_WEBHOOK, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYPERLEZ_SIGNATURE' => $header], $body);
}

beforeEach(function (): void {
    config(['services.payperlez.webhook_secret' => 'whsec_test']);
    RateLimiter::clear('webhooks');
});

describe('a signed event', function (): void {
    it('is kept encrypted exactly as it arrived and answered 200 at once', function (): void {
        Queue::fake();
        $payload = ['id' => 'evt_1', 'type' => 'collection.succeeded', 'data' => ['customer_msisdn' => '0244123456']];

        payperlezPost($payload)->assertOk()->assertExactJson(['status' => 'ok']);

        $log = WebhookLog::query()->sole();
        expect($log->source)->toBe(WebhookSource::Payperlez)->and($log->event)->toBe('collection.succeeded')->and($log->event_id)->toBe('evt_1')
            ->and($log->processed)->toBeFalse()->and(json_decode($log->payload, true))->toBe($payload)
            ->and(DB::table('webhook_logs')->value('payload'))->not->toContain('0244123456');
        Queue::assertNothingPushed();
    });

    it('keeps one copy of an event that is delivered again, and still answers 200', function (): void {
        $payload = ['id' => 'evt_1', 'type' => 'collection.succeeded'];

        payperlezPost($payload)->assertOk();
        payperlezPost($payload)->assertOk()->assertExactJson(['status' => 'ok']);

        expect(WebhookLog::query()->count())->toBe(1);
    });

    it('keeps different events with different ids', function (): void {
        payperlezPost(['id' => 'evt_1', 'type' => 'a'])->assertOk();
        payperlezPost(['id' => 'evt_2', 'type' => 'a'])->assertOk();

        expect(WebhookLog::query()->count())->toBe(2);
    });

    it('keeps a body with no id or event name, and one that is not JSON, still answering 200', function (string $body): void {
        payperlezPost($body)->assertOk();

        expect(WebhookLog::query()->sole()->event)->toBe('unnamed')->and(WebhookLog::query()->sole()->event_id)->toBeNull();
    })->with(['no fields' => '{}', 'not JSON' => 'not json at all', 'an id too long to be one' => '{"id":"'.str_repeat('x', 101).'"}']);
});

describe('an event that is not proved', function (): void {
    it('is refused with 401 and keeps nothing', function (array $signing): void {
        // A time is given as seconds from now and turned into a real time here, because a dataset is built when the files load, long before a full run reaches this test.
        if (isset($signing['time'])) {
            $signing['time'] += time();
        }

        payperlezPost(['id' => 'e'], ...$signing)->assertUnauthorized();

        expect(WebhookLog::query()->count())->toBe(0);
    })->with([
        'a wrong secret' => [['secret' => 'whsec_other']],
        'a signature from over 5 minutes ago' => [['time' => -400]],
        'a signature from over 5 minutes ahead' => [['time' => 400]],
        'no header' => [['header' => '']],
        'a header with no time' => [['header' => 'v1=abc']],
        'a time that is not a number' => [['header' => 't=abc,v1='.hash_hmac('sha256', 'abc.{"id":"e"}', 'whsec_test')]],
    ]);

    it('refuses a body changed after it was signed', function (): void {
        $time = time();

        payperlezPost('{"id":"e"}', header: 't='.$time.',v1='.hash_hmac('sha256', $time.'.{"id":"x"}', 'whsec_test'))->assertUnauthorized();

        expect(WebhookLog::query()->count())->toBe(0);
    });

    it('accepts a signature just inside the 5 minutes', function (): void {
        payperlezPost(['id' => 'e'], time() - 299)->assertOk();
    });

    it('refuses everything when no secret is set, even one signed with an empty secret', function (): void {
        config(['services.payperlez.webhook_secret' => '']);

        payperlezPost(['id' => 'e'], secret: '')->assertUnauthorized();

        expect(WebhookLog::query()->count())->toBe(0);
    });
});

describe('the limits and the address', function (): void {
    it('is limited per address like the other webhooks', function (): void {
        config(['limits.webhooks_per_minute' => 2]);

        payperlezPost(['id' => 'a'])->assertOk();
        payperlezPost(['id' => 'b'])->assertOk();
        payperlezPost(['id' => 'c'])->assertStatus(429);
    });

    it('is not answered on a church address', function (): void {
        $body = '{"id":"e"}';
        $header = 't='.time().',v1='.hash_hmac('sha256', time().'.'.$body, 'whsec_test');

        $this->call('POST', str_replace('localhost', 'grace.localhost', PAYPERLEZ_WEBHOOK), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYPERLEZ_SIGNATURE' => $header], $body)->assertNotFound();
    });
});

describe('the log repository', function (): void {
    it('stores an event once and says so with null the second time, per source', function (): void {
        $logs = app(WebhookLogRepositoryInterface::class);

        $first = $logs->createOnce(WebhookSource::Payperlez, 'a', 'evt_1', '{}');
        $again = $logs->createOnce(WebhookSource::Payperlez, 'a', 'evt_1', '{}');
        $otherSource = $logs->createOnce(WebhookSource::Paystack, 'a', 'evt_1', '{}');

        expect($first)->toBeInstanceOf(WebhookLog::class)->and($again)->toBeNull()->and($otherSource)->toBeInstanceOf(WebhookLog::class)
            ->and(WebhookLog::query()->count())->toBe(2);
    });
});
