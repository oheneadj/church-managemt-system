<?php

declare(strict_types=1);

use App\Enums\StaffRole;
use App\Events\ChurchRegistered;
use App\Jobs\SendSmsJob;
use App\Models\Church;
use App\Models\FundType;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

// Starting a church (docs/11 story 2.1, QA-03 TC-03-01 to TC-03-04). The church and its first admin are
// created together, only after the admin's phone is proven with the texted code.

const REGISTER = 'http://localhost/api/v1/onboarding/churches';
const SEND_CODE = 'http://localhost/api/v1/onboarding/otp';

/**
 * Asks for a code for the phone and returns the six digits that were texted.
 */
function codeFor(string $phone): string
{
    Queue::fake();
    test()->postJson(SEND_CODE, ['admin_phone' => $phone])->assertOk();

    $code = '';
    Queue::assertPushed(SendSmsJob::class, function (SendSmsJob $job) use (&$code): bool {
        preg_match('/\b(\d{6})\b/', $job->sms->message, $matches);
        $code = $matches[1];

        return true;
    });

    return $code;
}

/**
 * A valid sign-up body for Victory Chapel (QA-03), with the real texted code unless one is given.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function signUp(array $overrides = []): array
{
    $phone = $overrides['admin_phone'] ?? '+233244300001';

    return [
        'church_name' => 'Victory Chapel International',
        'slug' => 'victory-chapel',
        'denomination' => 'Pentecostal',
        'city' => 'Kumasi',
        'member_number_prefix' => 'vci',
        'admin_name' => 'Kofi Mensah',
        'admin_phone' => $phone,
        'otp' => codeFor($phone),
        ...$overrides,
    ];
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    RateLimiter::clear('onboarding');
});

it('creates the church and its first admin and says where to sign in', function (): void {
    $response = $this->postJson(REGISTER, signUp())->assertCreated();

    $response->assertJsonPath('church.slug', 'victory-chapel')
        ->assertJsonPath('church.name', 'Victory Chapel International')
        ->assertJsonPath('church.member_number_prefix', 'VCI')
        ->assertJsonPath('church.url', 'http://victory-chapel.localhost')
        ->assertJsonPath('login_url', 'http://victory-chapel.localhost/login')
        ->assertJsonPath('admin.name', 'Kofi Mensah');

    $church = Church::query()->where('slug', 'victory-chapel')->firstOrFail();
    expect($church->plan->value)->toBe('starter')->and($church->is_active)->toBeTrue()->and($church->denomination)->toBe('Pentecostal');

    $admin = User::query()->withoutGlobalScopes()->where('church_id', $church->id)->firstOrFail();
    expect($admin->phone)->toBe('+233244300001')->and($admin->hasRole(StaffRole::ChurchAdmin))->toBeTrue();
});

it('uses the ids, never the integer keys, in the reply', function (): void {
    $response = $this->postJson(REGISTER, signUp())->assertCreated();

    expect($response->json('church.id'))->toMatch('/^[0-9a-z]{26}$/')
        ->and($response->json('admin.id'))->toMatch('/^[0-9a-z]{26}$/');
});

it('chooses the web address from the church name when none is typed', function (): void {
    $this->postJson(REGISTER, signUp(['slug' => null]))->assertCreated()->assertJsonPath('church.slug', 'victory-chapel-international');
});

it('adds a number to a chosen address that is already used when none is typed', function (): void {
    Church::factory()->create(['slug' => 'victory-chapel-international']);

    $this->postJson(REGISTER, signUp(['slug' => '']))->assertCreated()->assertJsonPath('church.slug', 'victory-chapel-international-2');
});

it('lets the new admin sign in on the new church', function (): void {
    $this->postJson(REGISTER, signUp())->assertCreated();
    Queue::fake();

    $this->postJson('http://victory-chapel.localhost/api/v1/auth/otp/request', ['phone' => '+233244300001'])->assertOk();

    Queue::assertPushed(SendSmsJob::class, fn (SendSmsJob $job): bool => $job->sms->churchId !== null);
});

it('starts the new church with the default fund types, in order, and only for that church', function (): void {
    $other = Church::factory()->create(['slug' => 'older']);

    $this->postJson(REGISTER, signUp())->assertCreated();

    $new = Church::query()->where('slug', 'victory-chapel')->firstOrFail();
    $names = FundType::query()->withoutGlobalScopes()->where('church_id', $new->id)->orderBy('sort_order')->pluck('name')->all();
    expect($names)->toBe(collect(config('giving.default_fund_types'))->pluck('name')->all())
        ->and(FundType::query()->withoutGlobalScopes()->where('church_id', $other->id)->count())->toBe(0);
});

it('starts the new church with no members and no data from other churches', function (): void {
    $other = Church::factory()->create(['slug' => 'older']);
    Member::factory()->for($other)->create();

    $this->postJson(REGISTER, signUp())->assertCreated();

    $new = Church::query()->where('slug', 'victory-chapel')->firstOrFail();
    expect(Member::query()->withoutGlobalScopes()->where('church_id', $new->id)->count())->toBe(0)
        ->and(User::query()->withoutGlobalScopes()->where('church_id', $new->id)->count())->toBe(1);
});

it('announces the start with a ChurchRegistered event', function (): void {
    Event::fake([ChurchRegistered::class]);

    $this->postJson(REGISTER, signUp())->assertCreated();

    Event::assertDispatched(ChurchRegistered::class);
});

it('logs an info line without personal details', function (): void {
    Log::spy();

    $this->postJson(REGISTER, signUp())->assertCreated();

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => $message === 'Church registered'
        && array_keys($context) === ['church_id', 'admin_id']);
});

it('refuses a wrong code and creates nothing', function (): void {
    $body = signUp();

    $this->postJson(REGISTER, [...$body, 'otp' => $body['otp'] === '000000' ? '111111' : '000000'])
        ->assertUnprocessable()->assertJson(['message' => 'Invalid code']);

    expect(Church::query()->count())->toBe(0);
});

it('refuses a code asked for by a different phone number', function (): void {
    $body = signUp(['admin_phone' => '+233244300001']);

    $this->postJson(REGISTER, [...$body, 'admin_phone' => '+233244300002'])->assertUnprocessable()->assertJson(['message' => 'Invalid code']);

    expect(Church::query()->count())->toBe(0);
});

it('refuses a code that was already used', function (): void {
    $body = signUp();
    $this->postJson(REGISTER, $body)->assertCreated();

    $this->postJson(REGISTER, [...$body, 'slug' => 'second-chapel'])->assertUnprocessable()->assertJson(['message' => 'Invalid code']);
});

it('refuses a code that has expired', function (): void {
    $body = signUp();
    Cache::forget('church:onboarding:otp:'.hash('sha256', '+233244300001'));

    $this->postJson(REGISTER, $body)->assertUnprocessable()->assertJson(['message' => 'Invalid code']);
});

it('locks the phone out after too many wrong codes', function (): void {
    $body = signUp();

    foreach (range(1, config('otp.max_attempts') - 1) as $attempt) {
        $this->postJson(REGISTER, [...$body, 'otp' => '000000'])->assertUnprocessable();
    }

    $this->postJson(REGISTER, [...$body, 'otp' => '000000'])->assertTooManyRequests();
    $this->postJson(REGISTER, $body)->assertTooManyRequests();
});

it('suggests free addresses when the typed one is taken and keeps the code usable', function (): void {
    Church::factory()->create(['slug' => 'grace-assembly']);
    $body = signUp(['slug' => 'grace-assembly']);

    $this->postJson(REGISTER, $body)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('slug')
        ->assertJsonPath('errors.slug.0', 'This web address is already taken. Try grace-assembly-gh or grace-assembly-church or grace-assembly-2.');

    // The code was not used up by the failed attempt.
    $this->postJson(REGISTER, [...$body, 'slug' => 'grace-assembly-gh'])->assertCreated();
});

it('treats the address of a deleted church as taken', function (): void {
    Church::factory()->create(['slug' => 'old-church'])->delete();

    $this->postJson(REGISTER, signUp(['slug' => 'old-church']))->assertUnprocessable()->assertJsonValidationErrors('slug');
});

it('keeps platform names for itself', function (string $slug): void {
    $this->postJson(REGISTER, signUp(['slug' => $slug]))->assertUnprocessable()->assertJsonValidationErrors('slug');
})->with(['www', 'api', 'admin', 'docs', 'login']);

it('rejects a badly formed slug', function (string $slug): void {
    $this->postJson(REGISTER, signUp(['slug' => $slug]))->assertUnprocessable()->assertJsonValidationErrors('slug');
})->with(['spaces and symbols' => ['Grace Assembly!!'], 'underscore' => ['grace_assembly'], 'double hyphen' => ['grace--assembly'], 'leading hyphen' => ['-grace'], 'too short' => ['ab'], 'too long' => [str_repeat('a', 41)]]);

it('treats a slug typed in capitals as lowercase', function (): void {
    $this->postJson(REGISTER, signUp(['slug' => 'Victory-Chapel']))->assertCreated()->assertJsonPath('church.slug', 'victory-chapel');
});

it('requires the fields it needs and reports each one', function (): void {
    $this->postJson(REGISTER, [])->assertUnprocessable()->assertJsonValidationErrors(['church_name', 'member_number_prefix', 'admin_name', 'admin_phone', 'otp']);
});

it('rejects a prefix that is not letters of the right length', function (string $prefix): void {
    $this->postJson(REGISTER, signUp(['member_number_prefix' => $prefix]))->assertUnprocessable()->assertJsonValidationErrors('member_number_prefix');
})->with(['digits' => ['G12'], 'one letter' => ['G'], 'too long' => ['ABCDEFG'], 'symbol' => ['GR-C']]);

it('limits how many churches one IP address can start in an hour', function (): void {
    foreach (range(1, config('limits.onboarding_register_per_hour_per_ip')) as $number) {
        $this->postJson(REGISTER, [])->assertUnprocessable();
    }

    $this->postJson(REGISTER, [])->assertTooManyRequests();
});

it('is not available on a church subdomain', function (): void {
    Church::factory()->create(['slug' => 'grace']);

    $this->postJson('http://grace.localhost/api/v1/onboarding/churches', [])->assertNotFound();
});

it('serves the app page on the platform host without a church', function (): void {
    $this->get('http://localhost/start')->assertOk()->assertViewIs('app');
});

it('still answers 404 for an unknown church subdomain', function (): void {
    $this->get('http://nobody.localhost/members')->assertNotFound();
});
