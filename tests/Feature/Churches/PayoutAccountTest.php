<?php

declare(strict_types=1);

use App\Contracts\DisbursementProvider;
use App\Enums\MomoNetwork;
use App\Enums\PayoutMethod;
use App\Exceptions\PaymentProviderUnavailableException;
use App\Exceptions\PaymentRejectedException;
use App\Jobs\VerifyPayoutAccountJob;
use App\Models\AuditLog;
use App\Models\Church;
use App\Models\User;
use App\Repositories\ChurchRepositoryInterface;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

// Where a church's digital gifts are paid out (docs/08 Payout Account, docs/11 story 4.1, D-069 step d2): the admin saves the
// account, a worker asks the provider to name it, and digital giving stays off until the provider has. The provider is faked.

const PAYOUT_ACCOUNT = 'http://grace.localhost/api/v1/church/payout-account';

/** A staff member of the church with one role. */
function payoutStaff(Church $church, string $role): User
{
    $user = User::factory()->for($church)->create();
    $user->assignRole($role);

    return $user;
}

/** A provider that answers the check with a name, or throws what it is given. */
function fakeProviderNaming(string|Throwable $answer): void
{
    $provider = Mockery::mock(DisbursementProvider::class);
    $provider->shouldReceive('verifyAccount')->andReturnUsing(fn () => $answer instanceof Throwable ? throw $answer : $answer);
    app()->instance(DisbursementProvider::class, $provider);
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace']);
    $this->other = Church::factory()->create(['slug' => 'other']);
    $this->admin = payoutStaff($this->church, 'church-admin');
    Sanctum::actingAs($this->admin, ['*']);
});

describe('reading', function (): void {
    it('says not set for a church that has no account', function (): void {
        $this->getJson(PAYOUT_ACCOUNT)->assertOk()->assertJsonPath('data.status', 'not_set')->assertJsonPath('data.method', null)->assertJsonPath('data.account_number_hint', null);
    });

    it('shows only the last three digits of the number, never the whole of it', function (): void {
        $this->church->update(['payout_method' => PayoutMethod::Momo, 'payout_network' => MomoNetwork::Mtn, 'payout_account_number' => '+233244123456']);

        $response = $this->getJson(PAYOUT_ACCOUNT)->assertOk()->assertJsonPath('data.account_number_hint', '***456')->assertJsonPath('data.status', 'checking');

        expect($response->getContent())->not->toContain('244123456');
    });

    it('is the signed-in church\'s own account (isolation)', function (): void {
        $this->other->update(['payout_method' => PayoutMethod::Momo, 'payout_network' => MomoNetwork::Mtn, 'payout_account_number' => '+233200000999', 'payout_verified_at' => now()]);

        $this->getJson(PAYOUT_ACCOUNT)->assertOk()->assertJsonPath('data.status', 'not_set');
    });

    it('is refused without a token and to staff who are not the church admin', function (): void {
        $this->app['auth']->forgetGuards();
        $this->getJson(PAYOUT_ACCOUNT)->assertUnauthorized();

        foreach (['finance-secretary', 'pastor', 'department-head'] as $role) {
            Sanctum::actingAs(payoutStaff($this->church, $role), ['*']);
            $this->getJson(PAYOUT_ACCOUNT)->assertForbidden();
        }
    });
});

describe('saving', function (): void {
    it('saves a wallet, answers 202 as checking and queues the check on the payments queue', function (): void {
        Queue::fake();

        $this->putJson(PAYOUT_ACCOUNT, ['method' => 'momo', 'network' => 'mtn', 'account_number' => '0244123456'])
            ->assertStatus(202)->assertJsonPath('data.status', 'checking')->assertJsonPath('data.account_number_hint', '***456')->assertJsonPath('data.network', 'mtn');

        $church = $this->church->fresh();
        expect($church->payout_account_number)->toBe('+233244123456')->and($church->payout_verified_at)->toBeNull()->and($church->payout_account_name)->toBeNull();
        Queue::assertPushedOn('payments', VerifyPayoutAccountJob::class, fn (VerifyPayoutAccountJob $job) => $job->churchId === $this->church->id && $job->accountNumber === '+233244123456');
    });

    it('saves a bank account with its bank code', function (): void {
        Queue::fake();

        $this->putJson(PAYOUT_ACCOUNT, ['method' => 'bank', 'bank_code' => '058', 'account_number' => '0123456789012'])->assertStatus(202)->assertJsonPath('data.bank_code', '058')->assertJsonPath('data.network', null);

        expect($this->church->fresh()->payout_method)->toBe(PayoutMethod::Bank);
    });

    it('clears the earlier name and check when the account changes, so giving stays off until it passes', function (): void {
        Queue::fake();
        $this->church->update(['payout_method' => PayoutMethod::Momo, 'payout_network' => MomoNetwork::Mtn, 'payout_account_number' => '+233200000111', 'payout_account_name' => 'GRACE ASSEMBLY', 'payout_verified_at' => now()]);

        $this->putJson(PAYOUT_ACCOUNT, ['method' => 'momo', 'network' => 'telecel', 'account_number' => '0200000222'])->assertStatus(202)->assertJsonPath('data.status', 'checking');

        expect($this->church->fresh()->payout_verified_at)->toBeNull()->and($this->church->fresh()->payout_account_name)->toBeNull();
    });

    it('does nothing when the same account is saved again', function (): void {
        Queue::fake();
        $this->church->update(['payout_method' => PayoutMethod::Momo, 'payout_network' => MomoNetwork::Mtn, 'payout_account_number' => '+233244123456', 'payout_account_name' => 'GRACE ASSEMBLY', 'payout_verified_at' => now()]);

        $this->putJson(PAYOUT_ACCOUNT, ['method' => 'momo', 'network' => 'mtn', 'account_number' => '0244123456'])->assertStatus(202)->assertJsonPath('data.status', 'verified');

        Queue::assertNothingPushed();
    });

    it('writes the change to the history with the number hidden', function (): void {
        Queue::fake();

        $this->putJson(PAYOUT_ACCOUNT, ['method' => 'momo', 'network' => 'mtn', 'account_number' => '0244123456'])->assertStatus(202);

        $log = AuditLog::query()->latest('id')->firstOrFail();
        expect(json_encode($log->getAttributes()))->toContain('***456')->not->toContain('244123456');
    });

    it('is refused without a token, to staff who are not the church admin, and changes nothing', function (): void {
        Queue::fake();
        $this->app['auth']->forgetGuards();
        $this->putJson(PAYOUT_ACCOUNT, ['method' => 'momo', 'network' => 'mtn', 'account_number' => '0244123456'])->assertUnauthorized();

        Sanctum::actingAs(payoutStaff($this->church, 'finance-secretary'), ['*']);
        $this->putJson(PAYOUT_ACCOUNT, ['method' => 'momo', 'network' => 'mtn', 'account_number' => '0244123456'])->assertForbidden();

        expect($this->church->fresh()->payout_method)->toBeNull();
        Queue::assertNothingPushed();
    });

    it('only ever changes the signed-in church (isolation)', function (): void {
        Queue::fake();

        $this->putJson(PAYOUT_ACCOUNT, ['method' => 'momo', 'network' => 'mtn', 'account_number' => '0244123456'])->assertStatus(202);

        expect($this->other->fresh()->payout_method)->toBeNull();
    });

    it('refuses what is missing or wrong', function (array $body, string $field): void {
        Queue::fake();

        $this->putJson(PAYOUT_ACCOUNT, $body)->assertUnprocessable()->assertJsonValidationErrors($field);

        Queue::assertNothingPushed();
    })->with([
        'no method' => [['network' => 'mtn', 'account_number' => '0244123456'], 'method'],
        'an unknown method' => [['method' => 'cheque', 'account_number' => '0244123456'], 'method'],
        'a wallet with no network' => [['method' => 'momo', 'account_number' => '0244123456'], 'network'],
        'an unknown network' => [['method' => 'momo', 'network' => 'vodafone', 'account_number' => '0244123456'], 'network'],
        'a wallet that is not a phone number' => [['method' => 'momo', 'network' => 'mtn', 'account_number' => 'abc'], 'account_number'],
        'a bank with no bank code' => [['method' => 'bank', 'account_number' => '0123456789'], 'bank_code'],
        'a bank code on a wallet' => [['method' => 'momo', 'network' => 'mtn', 'bank_code' => '058', 'account_number' => '0244123456'], 'bank_code'],
        'a network on a bank' => [['method' => 'bank', 'bank_code' => '058', 'network' => 'mtn', 'account_number' => '0123456789'], 'network'],
        'a bank number with letters' => [['method' => 'bank', 'bank_code' => '058', 'account_number' => '01234abc89'], 'account_number'],
        'no number' => [['method' => 'bank', 'bank_code' => '058'], 'account_number'],
    ]);
});

describe('the check', function (): void {
    beforeEach(function (): void {
        $this->church->update(['payout_method' => PayoutMethod::Momo, 'payout_network' => MomoNetwork::Mtn, 'payout_account_number' => '+233244123456']);
        $this->run = fn () => (new VerifyPayoutAccountJob($this->church->id, '+233244123456'))->handle(app(ChurchRepositoryInterface::class), app(DisbursementProvider::class));
    });

    it('keeps the name the provider gives and marks the account verified', function (): void {
        fakeProviderNaming('GRACE ASSEMBLY CHURCH');

        ($this->run)();

        $church = $this->church->fresh();
        expect($church->payoutStatus())->toBe('verified')->and($church->payout_account_name)->toBe('GRACE ASSEMBLY CHURCH')->and($church->payout_verified_at)->not->toBeNull()->and($church->payout_check_error)->toBeNull();
    });

    it('keeps the reason when the provider cannot name the account', function (): void {
        fakeProviderNaming(new PaymentRejectedException('PayPerlez could not name the account.'));

        ($this->run)();

        $church = $this->church->fresh();
        expect($church->payoutStatus())->toBe('failed')->and($church->payout_check_error)->toBe('PayPerlez could not name the account.')->and($church->payout_verified_at)->toBeNull();
    });

    it('lets the job try again when the provider is busy, and changes nothing', function (): void {
        fakeProviderNaming(new PaymentProviderUnavailableException('PayPerlez could not be reached.'));

        expect(fn () => ($this->run)())->toThrow(PaymentProviderUnavailableException::class);
        expect($this->church->fresh()->payoutStatus())->toBe('checking');
    });

    it('throws away an answer for an account the church has since changed', function (): void {
        fakeProviderNaming('OLD ACCOUNT NAME');
        $this->church->update(['payout_account_number' => '+233200000999']);

        ($this->run)();

        expect($this->church->fresh()->payout_verified_at)->toBeNull()->and($this->church->fresh()->payout_account_name)->toBeNull();
    });

    it('does nothing for a church with no account, a gone church or an account already verified', function (): void {
        $provider = Mockery::mock(DisbursementProvider::class);
        $provider->shouldNotReceive('verifyAccount');
        app()->instance(DisbursementProvider::class, $provider);
        $job = fn (string $id) => (new VerifyPayoutAccountJob($id, '+233244123456'))->handle(app(ChurchRepositoryInterface::class), $provider);

        $job('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $this->church->update(['payout_verified_at' => now()]);
        $job($this->church->id);
        $this->church->update(['payout_verified_at' => null, 'payout_method' => null]);
        $job($this->church->id);

        expect(true)->toBeTrue();
    });

    it('logs that the tries ran out without any account detail', function (): void {
        Log::spy();

        (new VerifyPayoutAccountJob($this->church->id, '+233244123456'))->failed(new RuntimeException('boom +233244123456'));

        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $m, array $c) => $c === ['church_id' => $this->church->id, 'error' => RuntimeException::class]);
    });
});

describe('the church model', function (): void {
    it('builds the account the provider takes, for a wallet and a bank, and none when incomplete', function (): void {
        $this->church->forceFill(['payout_method' => PayoutMethod::Momo, 'payout_network' => MomoNetwork::At, 'payout_account_number' => '+233270000001']);
        expect($this->church->payoutAccount()?->network)->toBe(MomoNetwork::At);

        $this->church->forceFill(['payout_method' => PayoutMethod::Bank, 'payout_bank_code' => '058', 'payout_account_number' => '0123456789']);
        expect($this->church->payoutAccount()?->bankCode)->toBe('058');

        $this->church->forceFill(['payout_bank_code' => null]);
        expect($this->church->payoutAccount())->toBeNull();

        $this->church->forceFill(['payout_method' => PayoutMethod::Momo, 'payout_network' => null]);
        expect($this->church->payoutAccount())->toBeNull();

        $this->church->forceFill(['payout_method' => null]);
        expect($this->church->payoutAccount())->toBeNull();
    });
});
