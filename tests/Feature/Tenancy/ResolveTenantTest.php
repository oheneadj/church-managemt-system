<?php

declare(strict_types=1);

use App\Http\Middleware\ResolveTenant;
use App\Models\Church;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::middleware(['tenant', 'auth:sanctum'])
        ->get('/api/v1/_probe', fn () => response()->json(['church' => app('current.church')->slug]));
    Route::middleware('tenant')
        ->get('/api/v1/_public-probe', fn () => response()->json(['church' => app('current.church')->slug]));
});

it('resolves the church from the subdomain', function (): void {
    Church::factory()->create(['slug' => 'grace']);

    $this->getJson('http://grace.localhost/api/v1/_public-probe')
        ->assertOk()
        ->assertJson(['church' => 'grace']);
});

it('resolves the church from a custom domain', function (): void {
    Church::factory()->create(['slug' => 'grace', 'custom_domain' => 'portal.grace.org.gh']);

    $this->getJson('http://portal.grace.org.gh/api/v1/_public-probe')
        ->assertOk()
        ->assertJson(['church' => 'grace']);
});

it('returns 404 for an unknown subdomain', function (): void {
    $this->getJson('http://nobody.localhost/api/v1/_public-probe')->assertNotFound();
});

it('returns 404 for a suspended church', function (): void {
    Church::factory()->suspended()->create(['slug' => 'closed']);

    $this->getJson('http://closed.localhost/api/v1/_public-probe')->assertNotFound();
});

it('rejects unauthenticated requests with 401', function (): void {
    Church::factory()->create(['slug' => 'grace']);

    $this->getJson('http://grace.localhost/api/v1/_probe')->assertUnauthorized();
});

it('accepts a church B token on church B', function (): void {
    Church::factory()->create(['slug' => 'church-a']);
    $churchB = Church::factory()->create(['slug' => 'church-b']);
    $token = User::factory()->for($churchB)->create()->createToken('test')->plainTextToken;

    $this->withToken($token)->getJson('http://church-b.localhost/api/v1/_probe')->assertOk();
});

it('does not accept a church B token on church A', function (): void {
    Church::factory()->create(['slug' => 'church-a']);
    $churchB = Church::factory()->create(['slug' => 'church-b']);
    $token = User::factory()->for($churchB)->create()->createToken('test')->plainTextToken;

    // The token is valid, but its owner is invisible to church A's scoped user lookup.
    $this->withToken($token)->getJson('http://church-a.localhost/api/v1/_probe')->assertUnauthorized();
});

it('runs the tenant middleware before Sanctum and every other middleware', function (): void {
    $priority = app(Kernel::class)->getMiddlewarePriority();

    expect($priority[0])->toBe(ResolveTenant::class);
});
