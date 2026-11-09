<?php

declare(strict_types=1);

use App\Actions\SmsCredits\SettleSmsCreditPurchaseAction;
use App\Console\Commands\CheckPendingSmsCreditPurchases;
use App\DTOs\Payments\CollectionResult;
use App\DTOs\SmsCredits\SettleSmsCreditPurchaseDTO;
use App\Enums\CreditLedgerType;
use App\Enums\ProviderPaymentStatus;
use App\Enums\SmsCreditPurchaseStatus;
use App\Exceptions\PaymentProviderUnavailableException;
use App\Jobs\CheckSmsCreditPurchaseJob;
use App\Models\Church;
use App\Models\SmsCreditLedger;
use App\Models\SmsCreditPurchase;
use Carbon\CarbonImmutable;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

// Buying SMS credits, the settling half (D-069 step c, QA-08 TC-08-13): what the provider's final word does to a purchase. The
// credits are added once, only for the amount asked for, and never for a payment that failed or is not finished. This is
// financial code, so every branch is covered.

const CREDIT_PPZ_BASE = 'https://ppz.test/api/v1';

/** What the provider says about a purchase's payment. Amount and reference default to a clean match. */
function creditPurchaseResult(SmsCreditPurchase $purchase, ProviderPaymentStatus $status, ?string $amount = null, ?string $clientReference = null, ?string $fee = '0.04'): CollectionResult
{
    return new CollectionResult('ppz-ref-9', $clientReference ?? $purchase->id, $status, $amount ?? $purchase->amount, $fee, $amount ?? $purchase->amount);
}

function settleCreditPurchase(SmsCreditPurchase $purchase, CollectionResult $result): ?SmsCreditPurchase
{
    return app(SettleSmsCreditPurchaseAction::class)->execute(new SettleSmsCreditPurchaseDTO($purchase->id, $result));
}

beforeEach(function (): void {
    $this->church = Church::factory()->create(['sms_credits' => 20]);
    $this->purchase = SmsCreditPurchase::factory()->create(['church_id' => $this->church->id, 'credits' => 50, 'amount' => '12.50']);
});

describe('a payment that succeeded', function (): void {
    it('adds the credits, writes one topup line and completes the purchase', function (): void {
        $settled = settleCreditPurchase($this->purchase, creditPurchaseResult($this->purchase, ProviderPaymentStatus::Succeeded));

        expect($settled->status)->toBe(SmsCreditPurchaseStatus::Completed)->and($settled->provider_reference)->toBe('ppz-ref-9')->and($settled->provider_fee)->toBe('0.04')
            ->and($settled->completed_at)->not->toBeNull()->and($this->church->fresh()->sms_credits)->toBe(70);
        $line = SmsCreditLedger::query()->sole();
        expect($line->type)->toBe(CreditLedgerType::Topup)->and($line->credits)->toBe(50)->and($line->balance_after)->toBe(70)->and($line->reference_id)->toBe($this->purchase->id)->and($line->church_id)->toBe($this->church->id);
    });

    it('adds the credits once however many times it is told', function (): void {
        $result = creditPurchaseResult($this->purchase, ProviderPaymentStatus::Succeeded);

        settleCreditPurchase($this->purchase, $result);
        settleCreditPurchase($this->purchase, $result);
        settleCreditPurchase($this->purchase, $result);

        expect($this->church->fresh()->sms_credits)->toBe(70)->and(SmsCreditLedger::query()->count())->toBe(1);
    });

    it('keeps no fee when the provider has not reported one yet', function (): void {
        $settled = settleCreditPurchase($this->purchase, creditPurchaseResult($this->purchase, ProviderPaymentStatus::Succeeded, fee: null));

        expect($settled->status)->toBe(SmsCreditPurchaseStatus::Completed)->and($settled->provider_fee)->toBeNull();
    });

    it('never touches another church', function (): void {
        $other = Church::factory()->create(['sms_credits' => 7]);

        settleCreditPurchase($this->purchase, creditPurchaseResult($this->purchase, ProviderPaymentStatus::Succeeded));

        expect($other->fresh()->sms_credits)->toBe(7);
    });

    it('adds nothing when the provider reports a different amount, and says so', function (string $amount): void {
        Log::spy();

        $settled = settleCreditPurchase($this->purchase, creditPurchaseResult($this->purchase, ProviderPaymentStatus::Succeeded, amount: $amount));

        expect($settled->status)->toBe(SmsCreditPurchaseStatus::Pending)->and($this->church->fresh()->sms_credits)->toBe(20)->and(SmsCreditLedger::query()->count())->toBe(0);
        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $message, array $context): bool => $message === 'A paid SMS credit purchase does not match what the provider reported' && $context === ['purchase_id' => $this->purchase->id, 'church_id' => $this->church->id]);
    })->with(['less' => '12.49', 'more' => '12.51', 'a different size of number' => '125.00']);

    it('accepts the same amount written another way', function (): void {
        $settled = settleCreditPurchase($this->purchase, creditPurchaseResult($this->purchase, ProviderPaymentStatus::Succeeded, amount: '12.5'));

        expect($settled->status)->toBe(SmsCreditPurchaseStatus::Completed);
    });

    it('adds nothing when the answer is about another payment, and says so', function (): void {
        Log::spy();

        $settled = settleCreditPurchase($this->purchase, creditPurchaseResult($this->purchase, ProviderPaymentStatus::Succeeded, clientReference: 'some-other-payment'));

        expect($settled->status)->toBe(SmsCreditPurchaseStatus::Pending)->and($this->church->fresh()->sms_credits)->toBe(20);
        Log::shouldHaveReceived('error')->once();
    });

    it('holds one topup line for a purchase in the database as well, for two checks that race', function (): void {
        $line = fn () => SmsCreditLedger::query()->create(['church_id' => $this->church->id, 'type' => CreditLedgerType::Topup, 'credits' => 50, 'balance_after' => 70, 'reference_id' => $this->purchase->id]);
        $line();

        expect($line)->toThrow(UniqueConstraintViolationException::class);
    });
});

describe('a payment that failed or is not finished', function (): void {
    it('fails the purchase and adds nothing', function (): void {
        $settled = settleCreditPurchase($this->purchase, creditPurchaseResult($this->purchase, ProviderPaymentStatus::Failed));

        expect($settled->status)->toBe(SmsCreditPurchaseStatus::Failed)->and($settled->failure_reason)->toBe(SettleSmsCreditPurchaseAction::FAILURE_REASON)->and($settled->provider_reference)->toBe('ppz-ref-9')
            ->and($this->church->fresh()->sms_credits)->toBe(20)->and(SmsCreditLedger::query()->count())->toBe(0);
    });

    it('leaves the purchase pending while the provider is still working or does not know', function (ProviderPaymentStatus $status): void {
        $settled = settleCreditPurchase($this->purchase, creditPurchaseResult($this->purchase, $status));

        expect($settled->status)->toBe(SmsCreditPurchaseStatus::Pending)->and($this->church->fresh()->sms_credits)->toBe(20);
    })->with([ProviderPaymentStatus::Pending, ProviderPaymentStatus::Unknown]);
});

describe('a purchase that is already settled', function (): void {
    it('is not changed by a later answer: a failed one stays failed, a completed one is not paid twice', function (): void {
        settleCreditPurchase($this->purchase, creditPurchaseResult($this->purchase, ProviderPaymentStatus::Failed));
        $afterFailure = settleCreditPurchase($this->purchase, creditPurchaseResult($this->purchase, ProviderPaymentStatus::Succeeded));

        expect($afterFailure->status)->toBe(SmsCreditPurchaseStatus::Failed)->and($this->church->fresh()->sms_credits)->toBe(20);

        $done = SmsCreditPurchase::factory()->create(['church_id' => $this->church->id, 'status' => SmsCreditPurchaseStatus::Completed]);
        settleCreditPurchase($done, creditPurchaseResult($done, ProviderPaymentStatus::Failed));

        expect($done->fresh()->status)->toBe(SmsCreditPurchaseStatus::Completed);
    });

    it('answers null for a purchase that does not exist', function (): void {
        $result = creditPurchaseResult($this->purchase, ProviderPaymentStatus::Succeeded);

        expect(app(SettleSmsCreditPurchaseAction::class)->execute(new SettleSmsCreditPurchaseDTO('01HZZZZZZZZZZZZZZZZZZZZZZZ', $result)))->toBeNull();
    });
});

describe('the purchase record', function (): void {
    it('keeps the phone encrypted and the amounts exact', function (): void {
        expect(DB::table('sms_credit_purchases')->value('phone'))->not->toContain('233244123456')->and($this->purchase->fresh()->phone)->toBe('+233244123456')->and($this->purchase->fresh()->amount)->toBe('12.50');
    });

    it('is soft deleted, never removed', function (): void {
        $this->purchase->delete();

        expect(SmsCreditPurchase::withTrashed()->count())->toBe(1)->and(SmsCreditPurchase::query()->count())->toBe(0);
    });
});

describe('the job that asks the provider', function (): void {
    beforeEach(function (): void {
        config(['services.payperlez.api_key' => 'ppz_test_key', 'services.payperlez.base_url' => CREDIT_PPZ_BASE]);
    });

    /** A PayPerlez status reply for this purchase. */
    function creditPurchaseStatusReply(SmsCreditPurchase $purchase, string $status, float $amount = 12.5): Illuminate\Http\Client\PromiseInterface|PromiseInterface
    {
        return Http::response(['success' => true, 'data' => ['reference' => 'ppz-ref-9', 'status' => $status, 'amount' => $amount, 'fee' => 0.04, 'received' => $amount, 'client_reference' => $purchase->id], 'meta' => ['request_id' => 'r']], 200);
    }

    it('asks by the purchase id and completes a paid purchase', function (): void {
        Http::fake([CREDIT_PPZ_BASE.'/status*' => creditPurchaseStatusReply($this->purchase, 'succeeded')]);

        app()->call([new CheckSmsCreditPurchaseJob($this->purchase->id), 'handle']);

        Http::assertSent(fn ($request): bool => $request['client_reference'] === $this->purchase->id);
        expect($this->purchase->fresh()->status)->toBe(SmsCreditPurchaseStatus::Completed)->and($this->church->fresh()->sms_credits)->toBe(70);
    });

    it('fails a purchase the provider says failed', function (): void {
        Http::fake([CREDIT_PPZ_BASE.'/status*' => creditPurchaseStatusReply($this->purchase, 'failed')]);

        app()->call([new CheckSmsCreditPurchaseJob($this->purchase->id), 'handle']);

        expect($this->purchase->fresh()->status)->toBe(SmsCreditPurchaseStatus::Failed);
    });

    it('credits nothing for an amount that is not the one asked for', function (): void {
        Http::fake([CREDIT_PPZ_BASE.'/status*' => creditPurchaseStatusReply($this->purchase, 'succeeded', 1.0)]);

        app()->call([new CheckSmsCreditPurchaseJob($this->purchase->id), 'handle']);

        expect($this->purchase->fresh()->status)->toBe(SmsCreditPurchaseStatus::Pending)->and($this->church->fresh()->sms_credits)->toBe(20);
    });

    it('asks nothing for a purchase that is settled or missing', function (): void {
        Http::fake();
        $this->purchase->update(['status' => SmsCreditPurchaseStatus::Completed]);

        app()->call([new CheckSmsCreditPurchaseJob($this->purchase->id), 'handle']);
        app()->call([new CheckSmsCreditPurchaseJob('01HZZZZZZZZZZZZZZZZZZZZZZZ'), 'handle']);

        Http::assertNothingSent();
    });

    it('leaves the purchase pending and logs when the provider refuses the question', function (): void {
        Http::fake([CREDIT_PPZ_BASE.'/status*' => Http::response(['success' => false, 'error' => ['code' => 'not_found']], 404)]);
        Log::spy();

        app()->call([new CheckSmsCreditPurchaseJob($this->purchase->id), 'handle']);

        expect($this->purchase->fresh()->status)->toBe(SmsCreditPurchaseStatus::Pending);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'The provider would not say where an SMS credit purchase stands' && $context === ['purchase_id' => $this->purchase->id, 'church_id' => $this->church->id]);
    });

    it('lets a busy provider be tried again, and logs without anything personal when the tries run out', function (): void {
        Http::fake([CREDIT_PPZ_BASE.'/status*' => Http::response('', 503)]);
        Log::spy();

        expect(fn () => app()->call([new CheckSmsCreditPurchaseJob($this->purchase->id), 'handle']))->toThrow(PaymentProviderUnavailableException::class);

        (new CheckSmsCreditPurchaseJob($this->purchase->id))->failed(new RuntimeException('x'));
        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $message, array $context): bool => $message === 'An SMS credit purchase could not be checked' && $context === ['purchase_id' => $this->purchase->id, 'error' => RuntimeException::class]);
        expect($this->purchase->fresh()->status)->toBe(SmsCreditPurchaseStatus::Pending);
    });
});

describe('the every-minute check', function (): void {
    beforeEach(function (): void {
        CarbonImmutable::setTestNow('2026-11-09 12:00:00');
        Queue::fake();
    });

    afterEach(fn () => CarbonImmutable::setTestNow());

    it('queues a check, on the payments queue, only for pending purchases that are old enough', function (): void {
        $old = SmsCreditPurchase::factory()->create(['church_id' => $this->church->id, 'created_at' => now()->subMinutes(5)]);
        $fresh = SmsCreditPurchase::factory()->create(['church_id' => $this->church->id, 'created_at' => now()->subSeconds(10)]);
        $done = SmsCreditPurchase::factory()->create(['church_id' => $this->church->id, 'created_at' => now()->subMinutes(5), 'status' => SmsCreditPurchaseStatus::Completed]);
        $failed = SmsCreditPurchase::factory()->create(['church_id' => $this->church->id, 'created_at' => now()->subMinutes(5), 'status' => SmsCreditPurchaseStatus::Failed]);
        $this->purchase->forceFill(['created_at' => now()->subMinutes(4)])->save();

        $this->artisan('credits:check-pending-purchases')->assertSuccessful();

        Queue::assertPushedOn('payments', CheckSmsCreditPurchaseJob::class);
        $queued = Queue::pushed(CheckSmsCreditPurchaseJob::class)->map(fn (CheckSmsCreditPurchaseJob $job): string => $job->purchaseId)->sort()->values()->all();
        expect($queued)->toBe(collect([$old->id, $this->purchase->id])->sort()->values()->all())->and($queued)->not->toContain($fresh->id)->not->toContain($done->id)->not->toContain($failed->id);
    });

    it('reports purchases pending for too long, and stays quiet otherwise', function (): void {
        Log::spy();
        $this->purchase->forceFill(['created_at' => now()->subMinutes(30)])->save();

        $this->artisan('credits:check-pending-purchases')->assertSuccessful();
        Log::shouldNotHaveReceived('warning');

        $this->purchase->forceFill(['created_at' => now()->subMinutes(61)])->save();
        $this->artisan('credits:check-pending-purchases')->assertSuccessful();

        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => $message === 'SMS credit purchases have been pending for too long' && $context === ['count' => 1]);
    });

    it('is run every minute', function (): void {
        $event = collect(app(Schedule::class)->events())->first(fn ($event): bool => str_contains((string) $event->command, 'credits:check-pending-purchases'));

        expect($event)->not->toBeNull()->and($event->expression)->toBe('* * * * *')->and(CheckPendingSmsCreditPurchases::class)->toBeString();
    });
});
