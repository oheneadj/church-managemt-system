<?php

declare(strict_types=1);

use App\Actions\SmsCredits\RefundSmsCreditsAction;
use App\Actions\SmsCredits\ReserveSmsCreditsAction;
use App\Contracts\SmsProvider;
use App\DTOs\OutboundSms;
use App\DTOs\SmsCredits\SmsCreditChange;
use App\DTOs\SmsReceipt;
use App\Enums\CreditLedgerType;
use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Enums\RecipientType;
use App\Exceptions\SmsRejectedException;
use App\Jobs\SendSmsJob;
use App\Models\Church;
use App\Models\MessageLog;
use App\Models\SmsCreditLedger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

// SMS credits (D-051, agreement section 10): a church-paid text is paid for before it is sent, once, and given back if
// it fails for good. Texts the platform pays for take no credits.

/** A message log row for a text, the way a receipt makes one before it is queued. */
function queuedLog(Church $church, string $content = 'Grace Assembly: GHS 150.00 received.'): MessageLog
{
    return MessageLog::factory()->create([
        'church_id' => $church->id, 'channel' => MessageChannel::Sms, 'message_type' => 'contribution_receipt', 'content' => $content, 'status' => MessageStatus::Queued,
    ]);
}

/** A church-paid text for this log row. */
function paidSms(Church $church, MessageLog $log, int $cost = 1): OutboundSms
{
    return new OutboundSms($church->id, '+233244123456', $log->content, 'contribution_receipt', RecipientType::Member, null, null, $log->id, $cost);
}

beforeEach(function (): void {
    $this->church = Church::factory()->create(['sms_credits' => 5]);
    $this->log = queuedLog($this->church);
    $this->change = new SmsCreditChange($this->church->id, 1, $this->log->id);
});

describe('taking credits', function (): void {
    it('lowers the balance and writes a ledger line with the new balance', function (): void {
        expect(app(ReserveSmsCreditsAction::class)->execute($this->change))->toBeTrue();

        $line = SmsCreditLedger::query()->sole();
        expect($this->church->fresh()->sms_credits)->toBe(4)
            ->and($line->type)->toBe(CreditLedgerType::Consumption)->and($line->credits)->toBe(-1)->and($line->balance_after)->toBe(4)
            ->and($line->reference_id)->toBe($this->log->id)->and($line->church_id)->toBe($this->church->id);
    });

    it('takes several credits for a long text', function (): void {
        expect(app(ReserveSmsCreditsAction::class)->execute(new SmsCreditChange($this->church->id, 3, $this->log->id)))->toBeTrue();

        expect($this->church->fresh()->sms_credits)->toBe(2)->and(SmsCreditLedger::query()->sole()->credits)->toBe(-3);
    });

    it('takes nothing, and says so, when the church has too few', function (): void {
        $this->church->update(['sms_credits' => 2]);

        expect(app(ReserveSmsCreditsAction::class)->execute(new SmsCreditChange($this->church->id, 3, $this->log->id)))->toBeFalse();

        expect($this->church->fresh()->sms_credits)->toBe(2)->and(SmsCreditLedger::query()->count())->toBe(0);
    });

    it('can spend the very last credit, and then refuses the next text', function (): void {
        $this->church->update(['sms_credits' => 1]);
        $second = queuedLog($this->church);

        expect(app(ReserveSmsCreditsAction::class)->execute($this->change))->toBeTrue()
            ->and(app(ReserveSmsCreditsAction::class)->execute(new SmsCreditChange($this->church->id, 1, $second->id)))->toBeFalse()
            ->and($this->church->fresh()->sms_credits)->toBe(0);
    });

    it('charges a message once, however many times it is asked', function (): void {
        foreach (range(1, 4) as $ignored) {
            expect(app(ReserveSmsCreditsAction::class)->execute($this->change))->toBeTrue();
        }

        expect($this->church->fresh()->sms_credits)->toBe(4)->and(SmsCreditLedger::query()->count())->toBe(1);
    });

    it('leaves another church\'s credits alone', function (): void {
        $other = Church::factory()->create(['sms_credits' => 9]);

        app(ReserveSmsCreditsAction::class)->execute($this->change);

        expect($other->fresh()->sms_credits)->toBe(9);
    });

    it('lets the database refuse a second charge for one message', function (): void {
        app(ReserveSmsCreditsAction::class)->execute($this->change);
        $again = fn () => DB::transaction(fn () => SmsCreditLedger::query()->create(['church_id' => $this->church->id, 'type' => CreditLedgerType::Consumption, 'credits' => -1, 'balance_after' => 3, 'reference_id' => $this->log->id]));

        expect($again)->toThrow(UniqueConstraintViolationException::class);
    });
});

describe('giving credits back', function (): void {
    it('returns the credits of a text that was paid for, with a ledger line', function (): void {
        app(ReserveSmsCreditsAction::class)->execute(new SmsCreditChange($this->church->id, 2, $this->log->id));

        expect(app(RefundSmsCreditsAction::class)->execute(new SmsCreditChange($this->church->id, 2, $this->log->id)))->toBeTrue();

        $refund = SmsCreditLedger::query()->where('type', CreditLedgerType::Refund)->sole();
        expect($this->church->fresh()->sms_credits)->toBe(5)->and($refund->credits)->toBe(2)->and($refund->balance_after)->toBe(5)->and($refund->reference_id)->toBe($this->log->id);
    });

    it('gives back only once', function (): void {
        app(ReserveSmsCreditsAction::class)->execute($this->change);

        expect(app(RefundSmsCreditsAction::class)->execute($this->change))->toBeTrue()
            ->and(app(RefundSmsCreditsAction::class)->execute($this->change))->toBeFalse()
            ->and($this->church->fresh()->sms_credits)->toBe(5);
    });

    it('gives back nothing for a text that was never paid for', function (): void {
        expect(app(RefundSmsCreditsAction::class)->execute($this->change))->toBeFalse();

        expect($this->church->fresh()->sms_credits)->toBe(5)->and(SmsCreditLedger::query()->count())->toBe(0);
    });
});

describe('the text job', function (): void {
    /** Fakes the provider so a test decides what it answers. */
    function providerThat(Closure $send): void
    {
        $provider = Mockery::mock(SmsProvider::class);
        $provider->shouldReceive('send')->andReturnUsing($send);
        app()->instance(SmsProvider::class, $provider);
    }

    it('pays for a church text, sends it and marks the log row sent', function (): void {
        providerThat(fn () => new SmsReceipt('ref-9', ['status' => 'queued']));

        SendSmsJob::dispatchSync(paidSms($this->church, $this->log));

        $log = $this->log->fresh();
        expect($this->church->fresh()->sms_credits)->toBe(4)->and($log->status)->toBe(MessageStatus::Sent)->and($log->provider_reference)->toBe('ref-9')->and($log->sent_at)->not->toBeNull()
            ->and(MessageLog::query()->count())->toBe(1);
    });

    it('does not send, and fails the log row, when the church cannot pay', function (): void {
        $this->church->update(['sms_credits' => 0]);
        $sent = false;
        providerThat(function () use (&$sent): SmsReceipt {
            $sent = true;

            return new SmsReceipt;
        });

        try {
            SendSmsJob::dispatchSync(paidSms($this->church, $this->log));
        } catch (Throwable) {
            // dispatchSync raises the job's failure; a worker would record it and move on.
        }

        expect($sent)->toBeFalse()->and($this->church->fresh()->sms_credits)->toBe(0)->and(SmsCreditLedger::query()->count())->toBe(0)
            ->and($this->log->fresh()->status)->toBe(MessageStatus::Failed);
    });

    it('gives the credits back when the text fails for good', function (): void {
        providerThat(fn () => throw new SmsRejectedException('refused'));

        try {
            SendSmsJob::dispatchSync(paidSms($this->church, $this->log));
        } catch (Throwable) {
            // the failure is reported by the worker; here it reaches us
        }

        expect($this->church->fresh()->sms_credits)->toBe(5)->and($this->log->fresh()->status)->toBe(MessageStatus::Failed)
            ->and(SmsCreditLedger::query()->where('type', CreditLedgerType::Refund)->count())->toBe(1);
    });

    it('does not charge again when the job is run a second time', function (): void {
        providerThat(fn () => new SmsReceipt);
        $sms = paidSms($this->church, $this->log);

        SendSmsJob::dispatchSync($sms);
        SendSmsJob::dispatchSync($sms);

        expect($this->church->fresh()->sms_credits)->toBe(4)->and(SmsCreditLedger::query()->count())->toBe(1);
    });

    it('takes no credits for a text the platform pays for, such as a sign-in code', function (): void {
        providerThat(fn () => new SmsReceipt);
        $code = new OutboundSms($this->church->id, '+233244123456', 'Your code is 123456', 'otp', RecipientType::User, null, '[otp redacted]');

        SendSmsJob::dispatchSync($code);

        expect($this->church->fresh()->sms_credits)->toBe(5)->and(SmsCreditLedger::query()->count())->toBe(0)->and(MessageLog::query()->where('message_type', 'otp')->count())->toBe(1);
    });
});
