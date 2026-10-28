<?php

declare(strict_types=1);

use App\Enums\CheckInMethod;
use App\Enums\MemberStatus;
use App\Jobs\SendSmsJob;
use App\Models\AttendanceRecord;
use App\Models\Church;
use App\Models\Member;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;

// QR code check-in (docs/11 stories 3.4 and 3.11, QA-05 TC-05-09). The code on the screen proves presence, a texted code
// proves the phone, and both are short-lived and limited.

const QR = 'http://grace.localhost/api/v1';

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    RateLimiter::clear('checkin');
    $this->church = Church::factory()->create(['slug' => 'grace']);
    $this->admin = User::factory()->for($this->church)->create();
    $this->admin->assignRole('church-admin');
    $this->service = Service::factory()->open()->create(['church_id' => $this->church->id, 'name' => 'Sunday Morning Service']);
    $this->member = Member::factory()->create(['church_id' => $this->church->id, 'phone' => '+233244123456', 'first_name' => 'Emmanuel', 'preferred_name' => null]);
});

/** Asks for a fresh QR code as the admin, then drops the login so the next calls are the member's. */
function scannedToken(Service $service, User $admin): string
{
    Sanctum::actingAs($admin, ['*']);
    $token = test()->getJson(QR.'/services/'.$service->id.'/qr')->assertOk()->json('token');
    app('auth')->forgetGuards();

    return $token;
}

/** Asks for the check-in code as the member and returns the six digits that were texted. */
function textedCode(string $token, string $phone = '0244123456'): string
{
    Queue::fake();
    test()->postJson(QR.'/checkin/qr/otp', ['token' => $token, 'phone' => $phone])->assertOk();
    $code = '';
    Queue::assertPushed(SendSmsJob::class, function (SendSmsJob $job) use (&$code): bool {
        preg_match('/\b(\d{6})\b/', $job->sms->message, $m);
        $code = $m[1];

        return true;
    });

    return $code;
}

describe('the code on the screen', function (): void {
    it('gives a fresh code and the address to put in the QR code', function (): void {
        Sanctum::actingAs($this->admin, ['*']);

        $response = $this->getJson(QR.'/services/'.$this->service->id.'/qr')->assertOk();

        $token = $response->json('token');
        expect($token)->toHaveLength(config('attendance.qr_token_length'))
            ->and($response->json('url'))->toBe("http://grace.localhost/checkin/{$token}")
            ->and($response->json('expires_in'))->toBe(120)
            ->and($response->json('refresh_in'))->toBe(60);
    });

    it('gives a different code every time', function (): void {
        Sanctum::actingAs($this->admin, ['*']);

        $first = $this->getJson(QR.'/services/'.$this->service->id.'/qr')->json('token');
        $second = $this->getJson(QR.'/services/'.$this->service->id.'/qr')->json('token');

        expect($first)->not->toBe($second);
    });

    it('has nothing to show for a service that is not open (409)', function (): void {
        $this->service->update(['is_open' => false]);
        Sanctum::actingAs($this->admin, ['*']);

        $this->getJson(QR.'/services/'.$this->service->id.'/qr')->assertConflict()->assertJson(['message' => 'Check-in is closed for this service.']);
    });

    it('is for staff who may check people in, and needs a token', function (string $role, int $status): void {
        $staff = User::factory()->for($this->church)->create();
        $staff->assignRole($role);
        Sanctum::actingAs($staff, ['*']);

        $this->getJson(QR.'/services/'.$this->service->id.'/qr')->assertStatus($status);
    })->with([['church-admin', 200], ['pastor', 200], ['finance-secretary', 403], ['department-head', 403], ['youth-pastor', 403]]);

    it('is refused without a token and for another church\'s service', function (): void {
        $this->getJson(QR.'/services/'.$this->service->id.'/qr')->assertUnauthorized();

        Sanctum::actingAs($this->admin, ['*']);
        $theirs = Service::factory()->open()->create();
        $this->getJson(QR.'/services/'.$theirs->id.'/qr')->assertNotFound();
    });
});

describe('scanning', function (): void {
    it('tells the member which service the code is for, and nothing else', function (): void {
        $token = scannedToken($this->service, $this->admin);

        $response = $this->getJson(QR.'/checkin/qr/'.$token)->assertOk();

        expect($response->json())->toBe(['name' => 'Sunday Morning Service', 'type_label' => 'Sunday', 'service_date' => $this->service->service_date->toDateString(), 'is_open' => true]);
    });

    it('treats an unknown code as expired (404)', function (): void {
        $this->getJson(QR.'/checkin/qr/'.str_repeat('a', 32))->assertNotFound()->assertJson(['message' => 'This code has expired. Please scan the screen again.']);
    });

    it('stops working after two minutes', function (): void {
        $token = scannedToken($this->service, $this->admin);

        $this->travel(config('attendance.qr_ttl_seconds') + 1)->seconds();

        $this->getJson(QR.'/checkin/qr/'.$token)->assertNotFound();
    });

    it('still works shortly after the screen shows a newer code', function (): void {
        $old = scannedToken($this->service, $this->admin);
        $this->travel(config('attendance.qr_refresh_seconds'))->seconds();
        scannedToken($this->service, $this->admin);

        $this->getJson(QR.'/checkin/qr/'.$old)->assertOk();
    });

    it('does not open a church\'s service on another church\'s address', function (): void {
        $token = scannedToken($this->service, $this->admin);
        Church::factory()->create(['slug' => 'other']);

        $this->getJson('http://other.localhost/api/v1/checkin/qr/'.$token)->assertNotFound();
    });
});

describe('the texted code', function (): void {
    it('is sent on the critical queue to a member, with nothing readable in the log', function (): void {
        Queue::fake();
        $token = scannedToken($this->service, $this->admin);

        $this->postJson(QR.'/checkin/qr/otp', ['token' => $token, 'phone' => '024 412 3456'])->assertOk()->assertJson(['message' => 'OTP sent', 'expires_in' => 600]);

        Queue::assertPushedOn('critical', SendSmsJob::class, fn (SendSmsJob $job): bool => $job->sms->churchId === $this->church->id
            && $job->sms->phone === '+233244123456'
            && $job->sms->messageType === 'checkin_otp'
            && $job->sms->recipientId === $this->member->id
            && $job->sms->logContent === '[otp redacted]');
    });

    it('answers the same for a number that belongs to nobody, and sends nothing', function (): void {
        Queue::fake();
        $token = scannedToken($this->service, $this->admin);

        $this->postJson(QR.'/checkin/qr/otp', ['token' => $token, 'phone' => '0200999999'])->assertOk()->assertJson(['message' => 'OTP sent']);

        Queue::assertNothingPushed();
    });

    it('sends nothing for a member who has died', function (): void {
        Queue::fake();
        $this->member->update(['status' => MemberStatus::Deceased]);
        $token = scannedToken($this->service, $this->admin);

        $this->postJson(QR.'/checkin/qr/otp', ['token' => $token, 'phone' => '0244123456'])->assertOk();

        Queue::assertNothingPushed();
    });

    it('refuses an expired code, a closed service and a badly formed request', function (): void {
        Queue::fake();
        $token = scannedToken($this->service, $this->admin);

        $this->postJson(QR.'/checkin/qr/otp', ['token' => str_repeat('b', 32), 'phone' => '0244123456'])->assertNotFound();
        $this->postJson(QR.'/checkin/qr/otp', ['token' => 'short', 'phone' => '0244123456'])->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->postJson(QR.'/checkin/qr/otp', ['token' => $token, 'phone' => 'abc'])->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->service->update(['is_open' => false]);
        $this->postJson(QR.'/checkin/qr/otp', ['token' => $token, 'phone' => '0244123456'])->assertConflict();
        Queue::assertNothingPushed();
    });

    it('allows one text per phone per minute, so it cannot be used to run up the SMS bill', function (): void {
        Queue::fake();
        $token = scannedToken($this->service, $this->admin);

        $this->postJson(QR.'/checkin/qr/otp', ['token' => $token, 'phone' => '0244123456'])->assertOk();
        $this->postJson(QR.'/checkin/qr/otp', ['token' => $token, 'phone' => '0244123456'])->assertTooManyRequests();

        Queue::assertPushedTimes(SendSmsJob::class, 1);
    });
});

describe('checking in', function (): void {
    it('records the member by QR code and greets them by first name (QA-05 TC-05-09)', function (): void {
        $token = scannedToken($this->service, $this->admin);
        $code = textedCode($token);

        $this->postJson(QR.'/checkin/qr', ['token' => $token, 'phone' => '0244123456', 'otp' => $code])
            ->assertOk()
            ->assertExactJson(['checked_in' => true, 'already_checked_in' => false, 'service_name' => 'Sunday Morning Service', 'first_name' => 'Emmanuel']);

        $record = AttendanceRecord::query()->sole();
        expect($record->member_id)->toBe($this->member->id)->and($record->method)->toBe(CheckInMethod::Qr)->and($record->service_id)->toBe($this->service->id);
    });

    it('prefers the name the member is known by', function (): void {
        $this->member->update(['preferred_name' => 'Manny']);
        $token = scannedToken($this->service, $this->admin);
        $code = textedCode($token);

        $this->postJson(QR.'/checkin/qr', ['token' => $token, 'phone' => '0244123456', 'otp' => $code])->assertOk()->assertJsonPath('first_name', 'Manny');
    });

    it('scanning twice leaves one record and says they were already in', function (): void {
        $token = scannedToken($this->service, $this->admin);
        $this->postJson(QR.'/checkin/qr', ['token' => $token, 'phone' => '0244123456', 'otp' => textedCode($token)])->assertOk();
        RateLimiter::clear('checkin');

        $this->travel(61)->seconds();
        $second = $this->postJson(QR.'/checkin/qr', ['token' => $token, 'phone' => '0244123456', 'otp' => textedCode($token)])->assertOk();

        expect($second->json('already_checked_in'))->toBeTrue()->and(AttendanceRecord::query()->count())->toBe(1);
    });

    it('refuses a wrong code and records nothing', function (): void {
        $token = scannedToken($this->service, $this->admin);
        $code = textedCode($token);

        $this->postJson(QR.'/checkin/qr', ['token' => $token, 'phone' => '0244123456', 'otp' => $code === '000000' ? '111111' : '000000'])->assertUnprocessable()->assertJson(['message' => 'Invalid code']);

        expect(AttendanceRecord::query()->count())->toBe(0);
    });

    it('does not accept the code twice', function (): void {
        $token = scannedToken($this->service, $this->admin);
        $code = textedCode($token);
        $this->postJson(QR.'/checkin/qr', ['token' => $token, 'phone' => '0244123456', 'otp' => $code])->assertOk();

        $this->postJson(QR.'/checkin/qr', ['token' => $token, 'phone' => '0244123456', 'otp' => $code])->assertUnprocessable();
    });

    it('does not accept a code texted to another phone', function (): void {
        Member::factory()->create(['church_id' => $this->church->id, 'phone' => '+233244000111']);
        $token = scannedToken($this->service, $this->admin);
        $code = textedCode($token, '0244000111');

        $this->postJson(QR.'/checkin/qr', ['token' => $token, 'phone' => '0244123456', 'otp' => $code])->assertUnprocessable();
        expect(AttendanceRecord::query()->count())->toBe(0);
    });

    it('does not accept a code texted for a different service', function (): void {
        $other = Service::factory()->open()->create(['church_id' => $this->church->id]);
        $tokenA = scannedToken($this->service, $this->admin);
        $tokenB = scannedToken($other, $this->admin);
        $codeForA = textedCode($tokenA);

        $this->postJson(QR.'/checkin/qr', ['token' => $tokenB, 'phone' => '0244123456', 'otp' => $codeForA])->assertUnprocessable();
    });

    it('locks the phone out after too many wrong codes', function (): void {
        $token = scannedToken($this->service, $this->admin);
        $code = textedCode($token);
        $wrong = $code === '000000' ? '111111' : '000000';

        foreach (range(1, config('otp.max_attempts') - 1) as $ignored) {
            $this->postJson(QR.'/checkin/qr', ['token' => $token, 'phone' => '0244123456', 'otp' => $wrong])->assertUnprocessable();
        }
        RateLimiter::clear('checkin');

        $this->postJson(QR.'/checkin/qr', ['token' => $token, 'phone' => '0244123456', 'otp' => $wrong])->assertTooManyRequests();
        $this->postJson(QR.'/checkin/qr', ['token' => $token, 'phone' => '0244123456', 'otp' => $code])->assertTooManyRequests();
    });

    it('refuses an expired screen code, even with the right texted code', function (): void {
        $token = scannedToken($this->service, $this->admin);
        $code = textedCode($token);
        $this->travel(config('attendance.qr_ttl_seconds') + 1)->seconds();

        $this->postJson(QR.'/checkin/qr', ['token' => $token, 'phone' => '0244123456', 'otp' => $code])->assertNotFound();
    });

    it('refuses once the service is closed', function (): void {
        $token = scannedToken($this->service, $this->admin);
        $code = textedCode($token);
        $this->service->update(['is_open' => false]);

        $this->postJson(QR.'/checkin/qr', ['token' => $token, 'phone' => '0244123456', 'otp' => $code])->assertConflict();
        expect(AttendanceRecord::query()->count())->toBe(0);
    });

    it('rejects a badly formed request', function (array $body, string $field): void {
        $token = scannedToken($this->service, $this->admin);

        $this->postJson(QR.'/checkin/qr', ['token' => $token, 'phone' => '0244123456', 'otp' => '123456', ...$body])->assertUnprocessable()->assertJsonValidationErrors($field);
    })->with([[['otp' => '12'], 'otp'], [['otp' => 'abcdef'], 'otp'], [['phone' => 'abc'], 'phone'], [['token' => 'x'], 'token']]);

    it('cannot check a member in at another church', function (): void {
        $otherChurch = Church::factory()->create(['slug' => 'other']);
        $stranger = Member::factory()->create(['church_id' => $otherChurch->id, 'phone' => '+233244123456']);
        $token = scannedToken($this->service, $this->admin);
        $code = textedCode($token);

        $this->postJson('http://other.localhost/api/v1/checkin/qr', ['token' => $token, 'phone' => '0244123456', 'otp' => $code])->assertNotFound();
        expect(AttendanceRecord::query()->withoutGlobalScopes()->where('member_id', $stranger->id)->count())->toBe(0);
    });
});
