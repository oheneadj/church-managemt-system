<?php

declare(strict_types=1);

use App\Actions\Contributions\SendContributionReceiptAction;
use App\Contracts\SmsProvider;
use App\DTOs\OutboundSms;
use App\DTOs\SmsReceipt;
use App\Enums\CreditLedgerType;
use App\Enums\MemberStatus;
use App\Enums\MessageStatus;
use App\Enums\PaymentMethod;
use App\Events\ContributionReceived;
use App\Exceptions\SmsRejectedException;
use App\Jobs\SendSmsJob;
use App\Listeners\SendContributionReceipt;
use App\Models\Church;
use App\Models\Contribution;
use App\Models\FundType;
use App\Models\Member;
use App\Models\MessageLog;
use App\Models\SmsCreditLedger;
use App\Models\User;
use App\Services\SmsSegmenter;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

// Gift receipts by SMS (docs/11 story 4.7, docs/04 Giving Receipts, QA-06 TC-06-02, QA-08 TC-08-03 to TC-08-06 and
// TC-08-12, D-051). Recording a gift fires an event; a queued listener texts the member, paid for from the church's
// credits. The provider is faked, so no real text is ever sent.

const RECEIPT_GIFTS = 'http://grace.localhost/api/v1/contributions';

/** Records a cash gift through the API with a fresh Idempotency-Key. */
function recordForReceipt(array $body = []): TestResponse
{
    return test()->postJson(RECEIPT_GIFTS, [
        'member_id' => test()->member->id, 'fund_type_id' => test()->fund->id, 'amount' => '150.00', 'payment_method' => 'cash',
        'contributed_at' => '2026-10-25T10:00:00Z', ...$body,
    ], ['Idempotency-Key' => 'receipt-'.Str::random(20)]);
}

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-28 12:00:00', 'UTC'));
    $this->seed(RolesAndPermissionsSeeder::class);
    Cache::flush();
    $this->church = Church::factory()->create(['slug' => 'grace', 'name' => 'Grace Assembly', 'sms_credits' => 10]);
    $this->finance = User::factory()->for($this->church)->create();
    $this->finance->assignRole('finance-secretary');
    $this->fund = FundType::factory()->create(['church_id' => $this->church->id, 'name' => 'Tithe']);
    $this->member = Member::factory()->create(['church_id' => $this->church->id, 'phone' => '+233244123456', 'sms_opted_in' => true]);
    Sanctum::actingAs($this->finance, ['*']);

    // A provider that remembers what it was asked to send.
    $this->texts = new ArrayObject;
    $texts = $this->texts;
    $provider = Mockery::mock(SmsProvider::class);
    $provider->shouldReceive('send')->andReturnUsing(function (OutboundSms $sms) use ($texts): SmsReceipt {
        $texts->append($sms);

        return new SmsReceipt('ref-'.count($texts));
    });
    app()->instance(SmsProvider::class, $provider);
});

describe('the receipt (TC-06-01, TC-06-02, TC-08-03)', function (): void {
    it('texts the member the amount, fund, date and the month\'s giving, and charges one credit', function (): void {
        recordForReceipt()->assertCreated();

        expect($this->texts)->toHaveCount(1);
        $sms = $this->texts[0];
        expect($sms->phone)->toBe('+233244123456')
            ->and($sms->message)->toBe("Grace Assembly: GHS 150.00 received (Tithe) on 25 Oct 2026.\nYour total giving in October: GHS 150.00.\nThank you for your faithfulness.")
            ->and($sms->messageType)->toBe('contribution_receipt');
        $log = MessageLog::query()->sole();
        expect($log->message_type)->toBe('contribution_receipt')->and($log->status)->toBe(MessageStatus::Sent)->and($log->recipient_id)->toBe($this->member->id)
            ->and($log->content)->toBe($sms->message)->and($log->sent_at)->not->toBeNull();
        $line = SmsCreditLedger::query()->sole();
        expect($this->church->fresh()->sms_credits)->toBe(9)->and($line->type)->toBe(CreditLedgerType::Consumption)->and($line->credits)->toBe(-1)->and($line->reference_id)->toBe($log->id);
    });

    it('has no placeholder left in the text and stays in one part', function (): void {
        recordForReceipt()->assertCreated();

        expect($this->texts[0]->message)->not->toMatch('/:(church|amount|fund|date|month|total)\b/')->and(SmsCreditLedger::query()->sole()->credits)->toBe(-1);
    });

    it('goes on the notifications queue and is not sent before the gift is saved', function (): void {
        Queue::fake([SendSmsJob::class]);

        recordForReceipt()->assertCreated();

        Queue::assertPushedOn('notifications', SendSmsJob::class, fn (SendSmsJob $job): bool => $job->sms->creditCost === 1 && $job->sms->messageLogId === MessageLog::query()->sole()->id);
        expect($this->texts)->toHaveCount(0)->and(MessageLog::query()->sole()->status)->toBe(MessageStatus::Queued);
    });

    it('adds up the member\'s month from the gifts that stand', function (): void {
        $other = Member::factory()->create(['church_id' => $this->church->id]);
        $make = fn (Member $m, string $amount, string $when, array $extra = []) => Contribution::factory()->create(['church_id' => $this->church->id, 'fund_type_id' => $this->fund->id, 'member_id' => $m->id, 'amount' => $amount, 'contributed_at' => $when, ...$extra]);
        $make($this->member, '100.00', '2026-10-02 08:00:00');
        $make($this->member, '999.00', '2026-10-03 08:00:00', ['is_voided' => true, 'voided_at' => now(), 'void_reason' => 'Mistake']);
        $make($this->member, '50.00', '2026-09-30 23:00:00');
        $make($this->member, '60.00', '2026-11-01 00:00:00');
        $make($other, '400.00', '2026-10-10 08:00:00');

        recordForReceipt(['amount' => '25.50'])->assertCreated();

        expect($this->texts[0]->message)->toContain('Your total giving in October: GHS 125.50.');
    });

    it('names the month the gift was given in, so a late entry for last month says so', function (): void {
        recordForReceipt(['contributed_at' => '2026-09-14T09:00:00Z'])->assertCreated();

        expect($this->texts[0]->message)->toContain('on 14 Sep 2026')->toContain('Your total giving in September: GHS 150.00.');
    });

    it('uses the church\'s own calendar for the date and the month', function (): void {
        $this->church->update(['timezone' => 'Asia/Tokyo']);

        // 31 Oct 16:00 UTC is already 1 Nov in Tokyo.
        recordForReceipt(['contributed_at' => '2026-10-27T16:00:00Z'])->assertCreated();
        expect($this->texts[0]->message)->toContain('on 28 Oct 2026')->toContain('in October');
    });

    it('shows the fund name the gift was given to, even if the fund is renamed afterwards', function (): void {
        $id = recordForReceipt()->json('data.id');
        $this->fund->update(['name' => 'Renamed']);

        expect($this->texts[0]->message)->toContain('(Tithe)')->and(Contribution::query()->find($id)->fund_name)->toBe('Tithe');
    });
});

describe('who gets one', function (): void {
    it('skips an anonymous gift', function (): void {
        recordForReceipt(['member_id' => null])->assertCreated();

        expect($this->texts)->toHaveCount(0)->and(MessageLog::query()->count())->toBe(0)->and($this->church->fresh()->sms_credits)->toBe(10);
    });

    it('skips a member who turned SMS off, and a member who has died', function (string $case): void {
        match ($case) {
            'sms off' => $this->member->update(['sms_opted_in' => false]),
            'deceased' => $this->member->update(['status' => MemberStatus::Deceased]),
        };
        $contribution = Contribution::factory()->create(['church_id' => $this->church->id, 'fund_type_id' => $this->fund->id, 'member_id' => $this->member->id]);

        app(SendContributionReceiptAction::class)->execute($contribution);

        expect($this->texts)->toHaveCount(0)->and(MessageLog::query()->count())->toBe(0);
    })->with(['sms off', 'deceased']);

    it('skips a gift that was voided before the receipt was sent', function (): void {
        $contribution = Contribution::factory()->voided()->create(['church_id' => $this->church->id, 'fund_type_id' => $this->fund->id, 'member_id' => $this->member->id]);

        app(SendContributionReceiptAction::class)->execute($contribution);

        expect(MessageLog::query()->count())->toBe(0);
    });

    it('keeps cash receipts off when the church switched them off, but not digital gifts (TC-08-06)', function (): void {
        $this->church->update(['settings' => ['receipt_on_cash' => false]]);

        recordForReceipt()->assertCreated();
        expect($this->texts)->toHaveCount(0);

        $momo = Contribution::factory()->create(['church_id' => $this->church->id, 'fund_type_id' => $this->fund->id, 'member_id' => $this->member->id, 'payment_method' => PaymentMethod::Momo]);
        app(SendContributionReceiptAction::class)->execute($momo);
        expect($this->texts)->toHaveCount(1);
    });

    it('sends cash receipts when the setting has never been touched', function (): void {
        expect($this->church->sendsCashReceipts())->toBeTrue();
        $this->church->update(['settings' => ['absence_threshold' => 4]]);

        expect($this->church->fresh()->sendsCashReceipts())->toBeTrue();
    });
});

describe('credits (TC-08-12)', function (): void {
    it('sends no text, warns with ids only, and does not fail the gift, when the church has no credits', function (): void {
        $this->church->update(['sms_credits' => 0]);
        Log::spy();

        $id = recordForReceipt()->assertCreated()->json('data.id');

        expect($this->texts)->toHaveCount(0)->and(MessageLog::query()->count())->toBe(0)->and(SmsCreditLedger::query()->count())->toBe(0)->and(Contribution::query()->find($id))->not->toBeNull();
        Log::shouldHaveReceived('warning')->withArgs(fn (string $text, array $context): bool => $text === 'SMS send blocked: insufficient credits'
            && $context === ['church_id' => $this->church->id, 'contribution_id' => $id])->once();
    });

    it('spends the last credit on a receipt, and the next gift gets none', function (): void {
        $this->church->update(['sms_credits' => 1]);

        recordForReceipt()->assertCreated();
        recordForReceipt()->assertCreated();

        expect($this->texts)->toHaveCount(1)->and($this->church->fresh()->sms_credits)->toBe(0)->and(Contribution::query()->count())->toBe(2);
    });

    it('charges a long text for every part, counting the cheaper kind of text first', function (): void {
        $this->church->update(['name' => str_repeat('Grace ', 25).'Assembly']);

        recordForReceipt()->assertCreated();

        $parts = app(SmsSegmenter::class)->parts($this->texts[0]->message);
        expect($parts)->toBeGreaterThan(1)->and($this->church->fresh()->sms_credits)->toBe(10 - $parts)->and(SmsCreditLedger::query()->sole()->credits)->toBe(-$parts);
    });

    it('charges more when the church name has a Twi letter, because the text becomes Unicode', function (): void {
        $this->church->update(['name' => 'Ɔhene Grace Assembly International Central Church']);

        recordForReceipt()->assertCreated();

        expect($this->texts[0]->message)->toContain('Ɔhene')->and(SmsCreditLedger::query()->sole()->credits)->toBeLessThan(-1);
    });

    it('gives the credits back, and marks the log failed, when the text fails for good', function (): void {
        $provider = Mockery::mock(SmsProvider::class);
        $provider->shouldReceive('send')->andThrow(new SmsRejectedException('refused'));
        app()->instance(SmsProvider::class, $provider);

        try {
            recordForReceipt()->assertCreated();
        } catch (Throwable) {
            // a worker would report the failure and move on
        }

        expect($this->church->fresh()->sms_credits)->toBe(10)->and(MessageLog::query()->sole()->status)->toBe(MessageStatus::Failed)
            ->and(SmsCreditLedger::query()->where('type', CreditLedgerType::Refund)->count())->toBe(1);
    });
});

describe('once per gift', function (): void {
    it('queues one receipt however many times the work runs', function (): void {
        $id = recordForReceipt()->json('data.id');
        $gift = Contribution::query()->find($id);

        app(SendContributionReceiptAction::class)->execute($gift);
        app(SendContributionReceiptAction::class)->execute($gift->fresh());

        expect(MessageLog::query()->count())->toBe(1)->and($this->texts)->toHaveCount(1)->and($this->church->fresh()->sms_credits)->toBe(9)->and($gift->fresh()->receipt_queued_at)->not->toBeNull();
    });

    it('sends one receipt when a double click records one gift', function (): void {
        $key = ['Idempotency-Key' => 'same-receipt-key'];
        $body = ['member_id' => $this->member->id, 'fund_type_id' => $this->fund->id, 'amount' => '150.00', 'payment_method' => 'cash'];

        $this->postJson(RECEIPT_GIFTS, $body, $key)->assertCreated();
        $this->postJson(RECEIPT_GIFTS, $body, $key)->assertCreated();

        expect($this->texts)->toHaveCount(1)->and(Contribution::query()->count())->toBe(1);
    });
});

describe('the event and its listener', function (): void {
    it('fires when a gift is recorded, and not when the request is refused', function (): void {
        Event::fake([ContributionReceived::class]);

        recordForReceipt(['amount' => '0'])->assertUnprocessable();
        Event::assertNotDispatched(ContributionReceived::class);

        recordForReceipt()->assertCreated();
        Event::assertDispatched(ContributionReceived::class, fn (ContributionReceived $e): bool => $e->contribution->amount === '150.00');
    });

    it('is queued on the notifications queue and waits for the commit', function (): void {
        $listener = app(SendContributionReceipt::class);

        expect($listener)->toBeInstanceOf(ShouldQueue::class)->and($listener->queue)->toBe('notifications')->and($listener->afterCommit)->toBeTrue()
            ->and($listener->tries)->toBe(3)->and($listener->timeout)->toBe(60)->and($listener->backoff)->toBe([10, 30, 60]);
    });

    it('logs a failure with ids only', function (): void {
        Log::spy();
        $gift = Contribution::factory()->create(['church_id' => $this->church->id, 'fund_type_id' => $this->fund->id]);

        app(SendContributionReceipt::class)->failed(new ContributionReceived($gift), new RuntimeException('boom with +233244123456'));

        Log::shouldHaveReceived('error')->withArgs(fn (string $text, array $context): bool => $context === ['contribution_id' => $gift->id, 'error' => RuntimeException::class])->once();
    });

    it('works on a queue worker, where no church is in context, and leaves none behind', function (): void {
        $gift = Contribution::factory()->create(['church_id' => $this->church->id, 'fund_type_id' => $this->fund->id, 'member_id' => $this->member->id, 'amount' => '80.00', 'contributed_at' => '2026-10-20 09:00:00']);
        app()->forgetInstance('current.church');

        app(SendContributionReceipt::class)->handle(new ContributionReceived($gift));

        expect($this->texts)->toHaveCount(1)->and($this->texts[0]->message)->toContain('GHS 80.00 received')->and(app()->bound('current.church'))->toBeFalse()
            ->and($this->church->fresh()->sms_credits)->toBe(9);
    });
});

describe('churches stay apart (isolation)', function (): void {
    it('charges only the church of the gift and texts only its member', function (): void {
        $other = Church::factory()->create(['sms_credits' => 7]);

        recordForReceipt()->assertCreated();

        expect($other->fresh()->sms_credits)->toBe(7)->and(SmsCreditLedger::query()->withoutGlobalScopes()->where('church_id', $other->id)->count())->toBe(0)
            ->and(MessageLog::query()->withoutGlobalScopes()->sole()->church_id)->toBe($this->church->id);
    });
});
