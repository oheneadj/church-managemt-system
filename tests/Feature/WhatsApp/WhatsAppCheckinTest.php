<?php

declare(strict_types=1);

use App\Actions\WhatsApp\HandleWhatsAppCheckinAction;
use App\Contracts\WhatsAppGateway;
use App\DTOs\WhatsApp\InboundWhatsAppMessage;
use App\DTOs\WhatsApp\OutboundWhatsApp;
use App\DTOs\WhatsApp\WhatsAppReceipt;
use App\Enums\CheckInMethod;
use App\Enums\ConsentPurpose;
use App\Enums\MemberStatus;
use App\Models\AttendanceRecord;
use App\Models\Church;
use App\Models\Consent;
use App\Models\HandledWhatsappMessage;
use App\Models\Member;
use App\Models\MessageLog;
use App\Models\Service;
use App\Models\WebhookLog;
use App\Services\AttendanceRecorder;
use App\ValueObjects\PhoneNumber;
use Illuminate\Support\Facades\RateLimiter;

// WhatsApp CHECKIN (docs/11 story 3.3, QA-05 TC-05-03 to TC-05-07, TC-05-10, TC-05-13). These tests post signed
// payloads to the webhook and let the (synchronous) queue run the whole path, with a fake gateway catching the answers.

beforeEach(function (): void {
    config(['services.meta.verify_token' => 'verify-me', 'services.meta.app_secret' => 'app-secret', 'whatsapp.replies_per_minute_per_phone' => 5]);
    RateLimiter::clear('webhooks');
    RateLimiter::clear('whatsapp-reply:+233244123456');

    // A gateway that remembers what it was asked to send.
    $this->sent = new ArrayObject;
    $sent = $this->sent;
    app()->instance(WhatsAppGateway::class, new class($sent) implements WhatsAppGateway
    {
        public function __construct(private ArrayObject $sent) {}

        public function send(OutboundWhatsApp $message): WhatsAppReceipt
        {
            $this->sent->append($message);

            return new WhatsAppReceipt('wamid.reply');
        }
    });

    $this->church = Church::factory()->create(['slug' => 'grace']);
    $this->member = Member::factory()->create(['church_id' => $this->church->id, 'phone' => '+233244123456', 'first_name' => 'Emmanuel', 'preferred_name' => null]);
    $this->service = Service::factory()->open()->create(['church_id' => $this->church->id, 'name' => 'Sunday Morning Service', 'service_date' => '2026-10-25', 'checkin_code' => 'GRA7F2']);
});

/** The one answer sent, as text. */
function answer(): string
{
    expect(test()->sent)->toHaveCount(1);

    return test()->sent[0]->text;
}

describe('checking in (TC-05-03, TC-05-04)', function (): void {
    it('records the member, sends the welcome and counts them in', function (): void {
        metaPost(metaPayload('CHECKIN'))->assertOk();

        expect(answer())->toBe('✅ Checked in to Sunday Morning Service — 25 Oct 2026. Welcome, Emmanuel! God bless you. 🙏');
        $record = AttendanceRecord::query()->sole();
        expect($record->member_id)->toBe($this->member->id)
            ->and($record->service_id)->toBe($this->service->id)
            ->and($record->method)->toBe(CheckInMethod::Whatsapp);
        expect(WebhookLog::query()->sole()->processed)->toBeTrue();
    });

    it('uses the name the member goes by', function (): void {
        $this->member->update(['preferred_name' => 'Manny']);

        metaPost(metaPayload('CHECKIN'));

        expect(answer())->toContain('Welcome, Manny!');
    });

    it('accepts any capitals, extra spaces and the service code (TC-05-04)', function (string $text): void {
        $other = Service::factory()->open()->create(['church_id' => $this->church->id, 'name' => 'Midweek', 'checkin_code' => 'MID9X4']);

        metaPost(metaPayload($text));

        expect(answer())->toContain('Checked in to Sunday Morning Service')
            ->and(AttendanceRecord::query()->where('service_id', $this->service->id)->count())->toBe(1)
            ->and(AttendanceRecord::query()->where('service_id', $other->id)->count())->toBe(0);
    })->with(['  checkin gra7f2 ', 'CHECKIN GRA7F2', 'Checkin GRA7F2 please']);

    it('is for the service the code names even when another is open (TC-05-04)', function (): void {
        $midweek = Service::factory()->open()->create(['church_id' => $this->church->id, 'name' => 'Midweek', 'checkin_code' => 'MID9X4']);

        metaPost(metaPayload('CHECKIN MID9X4'));

        expect(answer())->toContain('Midweek')
            ->and(AttendanceRecord::query()->sole()->service_id)->toBe($midweek->id);
    });

    it('checks in a member who is inactive, but not one who has died', function (): void {
        $this->member->update(['status' => MemberStatus::Inactive]);
        metaPost(metaPayload('CHECKIN'));
        expect(AttendanceRecord::query()->count())->toBe(1);

        $this->member->update(['status' => MemberStatus::Deceased]);
        metaPost(metaPayload('CHECKIN', id: 'wamid.TWO'));
        expect(AttendanceRecord::query()->count())->toBe(1)->and($this->sent->getArrayCopy()[1]->text)->toContain("couldn't find your number");
    });
});

describe('the sender is not found (TC-05-05)', function (): void {
    it('tells them to contact the office and records nothing', function (): void {
        metaPost(metaPayload('CHECKIN', from: '233209999999'));

        expect(answer())->toBe("We couldn't find your number in our records. Please contact the church office.")
            ->and(AttendanceRecord::query()->count())->toBe(0);
    });

    it('does not keep the stranger\'s phone number in the message log', function (): void {
        metaPost(metaPayload('CHECKIN', from: '233209999999'));

        $log = MessageLog::query()->withoutGlobalScopes()->sole();
        expect($log->recipient_phone)->toBeNull()->and($log->church_id)->toBeNull();
    });
});

describe('no service is open (TC-05-06, TC-05-10)', function (): void {
    it('says so', function (): void {
        $this->service->update(['is_open' => false]);

        metaPost(metaPayload('CHECKIN'));

        expect(answer())->toBe('No service is currently open for check-in.')->and(AttendanceRecord::query()->count())->toBe(0);
    });

    it('says so for a code that belongs to no service', function (): void {
        metaPost(metaPayload('CHECKIN ZZZZZZ'));

        expect(answer())->toBe('No service is currently open for check-in.');
    });

    it('says so for the code of a closed service', function (): void {
        $this->service->update(['is_open' => false]);

        metaPost(metaPayload('CHECKIN GRA7F2'));

        expect(answer())->toBe('No service is currently open for check-in.')->and(AttendanceRecord::query()->count())->toBe(0);
    });
});

describe('sending it twice (TC-05-07)', function (): void {
    it('keeps one record when Meta delivers the same message again, and answers once', function (): void {
        metaPost(metaPayload('CHECKIN', id: 'wamid.SAME'));
        metaPost(metaPayload('CHECKIN', id: 'wamid.SAME'));

        expect(AttendanceRecord::query()->count())->toBe(1)
            ->and($this->sent)->toHaveCount(1)
            ->and(HandledWhatsappMessage::query()->where('message_id', 'wamid.SAME')->count())->toBe(1);
    });

    it('keeps one record when the member sends it again, and tells them they are in', function (): void {
        metaPost(metaPayload('CHECKIN', id: 'wamid.ONE'));
        metaPost(metaPayload('CHECKIN', id: 'wamid.TWO'));

        expect(AttendanceRecord::query()->count())->toBe(1)
            ->and($this->sent)->toHaveCount(2)
            ->and($this->sent[1]->text)->toBe("You're already checked in! See you inside. 🙏");
    });

    it('does not keep the message id when the work fails, so Meta\'s retry can succeed', function (): void {
        $recorder = Mockery::mock(AttendanceRecorder::class);
        $recorder->shouldReceive('recordMembers')->once()->andThrow(new RuntimeException('database went away'));
        app()->instance(AttendanceRecorder::class, $recorder);
        $message = new InboundWhatsAppMessage('wamid.FAIL', PhoneNumber::tryFrom('+233244123456'), 'CHECKIN');

        expect(fn () => app(HandleWhatsAppCheckinAction::class)->execute($message))->toThrow(RuntimeException::class);

        expect(HandledWhatsappMessage::query()->where('message_id', 'wamid.FAIL')->exists())->toBeFalse()
            ->and(AttendanceRecord::query()->count())->toBe(0)
            ->and($this->member->fresh()->whatsapp_opted_in)->toBeFalse();
    });
});

describe('two services (TC-05-13)', function (): void {
    it('goes to the service opened first when no code is sent', function (): void {
        $this->service->update(['checkin_opened_at' => now()->subHour()]);
        Service::factory()->open()->create(['church_id' => $this->church->id, 'name' => 'Second Service', 'checkin_opened_at' => now()->subMinutes(5)]);

        metaPost(metaPayload('CHECKIN'));

        expect(AttendanceRecord::query()->sole()->service_id)->toBe($this->service->id);
    });

    it('lets a member check in to both with separate records', function (): void {
        $second = Service::factory()->open()->create(['church_id' => $this->church->id, 'name' => 'Second Service', 'checkin_code' => 'SEC2ND']);

        metaPost(metaPayload('CHECKIN GRA7F2', id: 'wamid.A'));
        metaPost(metaPayload('CHECKIN SEC2ND', id: 'wamid.B'));

        expect(AttendanceRecord::query()->count())->toBe(2)
            ->and(AttendanceRecord::query()->where('service_id', $second->id)->count())->toBe(1);
    });
});

describe('churches stay apart', function (): void {
    it('does not check a member in to another church\'s service by its code (isolation)', function (): void {
        $other = Church::factory()->create(['slug' => 'other']);
        $foreign = Service::factory()->open()->create(['church_id' => $other->id, 'checkin_code' => 'OTH3R5']);

        metaPost(metaPayload('CHECKIN OTH3R5'));

        expect(answer())->toContain("couldn't find your number")
            ->and(AttendanceRecord::query()->withoutGlobalScopes()->where('service_id', $foreign->id)->count())->toBe(0)
            ->and(AttendanceRecord::query()->count())->toBe(0);
    });

    it('ignores another church\'s open service when the member belongs to one church', function (): void {
        $this->service->update(['is_open' => false]);
        $other = Church::factory()->create(['slug' => 'other']);
        Service::factory()->open()->create(['church_id' => $other->id]);

        metaPost(metaPayload('CHECKIN'));

        expect(answer())->toBe('No service is currently open for check-in.');
    });

    it('asks for the code when the number is in two churches that both have a service open', function (): void {
        $other = Church::factory()->create(['slug' => 'other']);
        Member::factory()->create(['church_id' => $other->id, 'phone' => '+233244123456']);
        $theirs = Service::factory()->open()->create(['church_id' => $other->id, 'checkin_code' => 'OTH3R5']);

        metaPost(metaPayload('CHECKIN'));

        expect(answer())->toContain('Please send CHECKIN followed by the service code')
            ->and(AttendanceRecord::query()->withoutGlobalScopes()->count())->toBe(0);

        $this->sent->exchangeArray([]);
        metaPost(metaPayload('CHECKIN OTH3R5', id: 'wamid.TWO'));

        expect(AttendanceRecord::query()->withoutGlobalScopes()->sole()->service_id)->toBe($theirs->id);
    });

    it('goes to the one church with a service open when the number is in two', function (): void {
        $other = Church::factory()->create(['slug' => 'other']);
        Member::factory()->create(['church_id' => $other->id, 'phone' => '+233244123456']);

        metaPost(metaPayload('CHECKIN'));

        expect(AttendanceRecord::query()->sole()->service_id)->toBe($this->service->id);
    });

    it('ignores a church that has been switched off', function (): void {
        $this->church->update(['is_active' => false]);

        metaPost(metaPayload('CHECKIN'));

        expect(answer())->toContain("couldn't find your number")->and(AttendanceRecord::query()->withoutGlobalScopes()->count())->toBe(0);
    });

    it('leaves no church in context for the next job', function (): void {
        metaPost(metaPayload('CHECKIN'));

        expect(app()->bound('current.church'))->toBeFalse();
    });
});

describe('agreeing to WhatsApp', function (): void {
    it('counts the first message as opting in, with a consent record', function (): void {
        metaPost(metaPayload('CHECKIN'));

        expect($this->member->fresh()->whatsapp_opted_in)->toBeTrue();
        $consent = Consent::query()->where('member_id', $this->member->id)->where('purpose', ConsentPurpose::Whatsapp)->sole();
        expect($consent->withdrawn_at)->toBeNull();
    });

    it('does not record a second agreement for a member who already agreed', function (): void {
        $this->member->update(['whatsapp_opted_in' => true]);

        metaPost(metaPayload('CHECKIN', id: 'wamid.A'));
        metaPost(metaPayload('CHECKIN', id: 'wamid.B'));

        expect(Consent::query()->where('purpose', ConsentPurpose::Whatsapp)->count())->toBe(0);
    });

    it('does not opt in a sender who is not a member', function (): void {
        metaPost(metaPayload('CHECKIN', from: '233209999999'));

        expect(Consent::query()->withoutGlobalScopes()->count())->toBe(0);
    });
});

describe('messages that are not a check-in', function (): void {
    it('are ignored: no record, no answer, nothing kept', function (string $text): void {
        metaPost(metaPayload($text));

        expect($this->sent)->toHaveCount(0)->and(AttendanceRecord::query()->count())->toBe(0)->and(HandledWhatsappMessage::query()->count())->toBe(0);
    })->with(['hello', 'please CHECKIN', 'CHECK IN', '']);

    it('are ignored when they are pictures or delivery receipts', function (): void {
        metaPost(metaPayload('CHECKIN', type: 'image'));
        metaPost(['entry' => [['changes' => [['value' => ['statuses' => [['id' => 'wamid.X', 'status' => 'read']]]]]]]]);

        expect($this->sent)->toHaveCount(0)->and(AttendanceRecord::query()->count())->toBe(0);
    });

    it('handles every message when Meta sends several at once', function (): void {
        $second = Member::factory()->create(['church_id' => $this->church->id, 'phone' => '+233201111111']);
        $payload = metaPayload('CHECKIN', id: 'wamid.A');
        $payload['entry'][0]['changes'][0]['value']['messages'][] = ['from' => '233201111111', 'id' => 'wamid.B', 'type' => 'text', 'text' => ['body' => 'checkin']];

        metaPost($payload);

        expect(AttendanceRecord::query()->pluck('member_id')->all())->toEqualCanonicalizing([$this->member->id, $second->id]);
    });
});

describe('the answers', function (): void {
    it('are logged against the member and the church', function (): void {
        metaPost(metaPayload('CHECKIN'));

        $log = MessageLog::query()->sole();
        expect($log->channel->value)->toBe('whatsapp')->and($log->recipient_id)->toBe($this->member->id)
            ->and($log->recipient_phone)->toBe('+233244123456')->and($log->message_type)->toBe('checkin_reply')
            ->and($log->status->value)->toBe('sent')->and($log->provider_reference)->toBe('wamid.reply');
    });

    it('stop after a few in a minute to one number, but the check-in still counts', function (): void {
        config(['whatsapp.replies_per_minute_per_phone' => 2]);
        foreach (range(1, 4) as $n) {
            metaPost(metaPayload('CHECKIN', id: "wamid.{$n}"));
        }

        expect($this->sent)->toHaveCount(2)->and(AttendanceRecord::query()->count())->toBe(1);
    });
});
