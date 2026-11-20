<?php

declare(strict_types=1);

use App\Models\Church;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

// Agreement section 11, layer 1: the same Idempotency-Key never does the same thing twice. A small route that counts
// how many times it ran stands in for any money-writing endpoint.

const PROBE = 'http://grace.localhost/api/v1/_idempotency-probe';

beforeEach(function (): void {
    Cache::flush();
    $this->runs = 0;
    $runs = &$this->runs;
    Route::middleware(['api', 'tenant', 'auth:sanctum', 'idempotent'])->prefix('api/v1')->group(function () use (&$runs): void {
        Route::post('_idempotency-probe', function () use (&$runs) {
            $runs++;

            return response()->json(['run' => $runs, 'echo' => request('value')], request('value') === 'bad' ? 422 : 201);
        })->name('idempotency.probe');
    });

    $this->church = Church::factory()->create(['slug' => 'grace']);
    $this->user = User::factory()->for($this->church)->create();
    Sanctum::actingAs($this->user, ['*']);
});

/** Posts the probe with a key. */
function probe(array $body = ['value' => 'a'], ?string $key = 'key-0000-0001'): TestResponse
{
    return test()->postJson(PROBE, $body, array_filter(['Idempotency-Key' => $key]));
}

it('does the work once and gives the first answer back for the same key', function (): void {
    $first = probe()->assertCreated();
    $second = probe()->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

    expect($this->runs)->toBe(1)->and($second->json())->toBe($first->json());
});

it('does the work again for a new key', function (): void {
    probe(key: 'key-0000-0001');
    probe(key: 'key-0000-0002');

    expect($this->runs)->toBe(2);
});

it('refuses the same key for a different request', function (): void {
    probe(['value' => 'a']);

    probe(['value' => 'b'])->assertUnprocessable()->assertJsonPath('message', 'This Idempotency-Key was already used for a different request.');
    expect($this->runs)->toBe(1);
});

it('requires a key of the right shape', function (?string $key): void {
    probe(key: $key)->assertStatus(400);

    expect($this->runs)->toBe(0);
})->with(['no key' => [null], 'too short' => ['abc'], 'too long' => [str_repeat('a', 101)], 'bad characters' => ['key with spaces!']]);

it('does not keep a failed answer, so the corrected request can use the same key', function (): void {
    probe(['value' => 'bad'])->assertUnprocessable();
    probe(['value' => 'good'])->assertCreated();

    expect($this->runs)->toBe(2);
});

it('keeps one person\'s keys apart from another\'s', function (): void {
    probe();
    Sanctum::actingAs(User::factory()->for($this->church)->create(), ['*']);
    probe();

    expect($this->runs)->toBe(2);
});

it('keeps one church\'s keys apart from another\'s', function (): void {
    probe();
    $other = Church::factory()->create(['slug' => 'other']);
    Sanctum::actingAs(User::factory()->for($other)->create(), ['*']);

    $this->postJson('http://other.localhost/api/v1/_idempotency-probe', ['value' => 'a'], ['Idempotency-Key' => 'key-0000-0001'])->assertCreated();

    expect($this->runs)->toBe(2);
});

it('forgets the key after the time limit', function (): void {
    probe();
    $this->travel(config('limits.idempotency_ttl_minutes') + 1)->minutes();

    probe();

    expect($this->runs)->toBe(2);
});

it('makes a second request with the same key wait for the first instead of racing it', function (): void {
    $lock = Cache::lock('idempotency:'.$this->church->getKey().':'.$this->user->getAuthIdentifier().':idempotency.probe:key-0000-0001:lock', 10);
    $lock->get();
    config(['limits.idempotency_lock_seconds' => 1]);

    probe()->assertStatus(409)->assertJsonPath('message', 'A request with this Idempotency-Key is still being processed. Try again in a moment.');

    expect($this->runs)->toBe(0);
    $lock->release();
});
