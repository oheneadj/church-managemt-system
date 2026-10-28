<?php

declare(strict_types=1);

use App\Models\Church;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace', 'name' => 'Grace Assembly']);
    $this->user = User::factory()->for($this->church)->create(['phone' => '+233244123456']);
    $this->user->assignRole('church-admin');
});

/** Headers a browser sends from the app's own page: the Origin is the same host as the request. */
function fromApp(string $slug = 'grace'): array
{
    return ['Origin' => "http://{$slug}.localhost", 'Accept' => 'application/json'];
}

/** Signs a user in the way the SPA does and returns the response plus the session id the browser keeps. */
function signInFromBrowser(Church $church, string $phone, string $slug = 'grace'): array
{
    Cache::put("church:{$church->id}:otp:".hash('sha256', $phone), Hash::make('123456'), 600);

    $response = test()->withHeaders(fromApp($slug))
        ->postJson("http://{$slug}.localhost/api/v1/auth/otp/verify", ['phone' => $phone, 'otp' => '123456']);
    $response->assertOk();

    return [$response, app('session.store')->getId()];
}

/**
 * A later request from the same browser. Every request in a test shares one app instance, so I clear the
 * auth guards and the session store first; in production each request starts with nothing loaded.
 * The harness encrypts the cookie, as the browser's copy would be.
 */
function asBrowser(string $sessionId, string $slug = 'grace'): TestCase
{
    app('auth')->forgetGuards();
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');

    // JSON requests only carry cookies when credentials are switched on, as a browser's fetch needs them too.
    return test()->withHeaders(fromApp($slug))->withCredentials()->withCookie(config('session.cookie'), $sessionId);
}

it('signs a browser in with a session cookie and no token', function (): void {
    [$response] = signInFromBrowser($this->church, '+233244123456');

    $response->assertJsonMissingPath('token')->assertJsonPath('user.id', $this->user->id)->assertJsonPath('church.slug', 'grace');
    expect($this->user->tokens()->count())->toBe(0);

    $cookie = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === config('session.cookie'));
    expect($cookie->isHttpOnly())->toBeTrue()->and($cookie->getDomain())->toBeNull();
});

it('keeps the user signed in on later requests, which is what survives a page refresh', function (): void {
    [, $cookie] = signInFromBrowser($this->church, '+233244123456');

    asBrowser($cookie)->getJson('http://grace.localhost/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $this->user->id)
        ->assertJsonPath('church.name', 'Grace Assembly');
});

it('rejects the same request without the cookie', function (): void {
    $this->withHeaders(fromApp())->getJson('http://grace.localhost/api/v1/auth/me')->assertUnauthorized();
});

it('ends the session on logout', function (): void {
    [, $cookie] = signInFromBrowser($this->church, '+233244123456');

    asBrowser($cookie)->postJson('http://grace.localhost/api/v1/auth/logout')->assertNoContent();

    asBrowser($cookie)->getJson('http://grace.localhost/api/v1/auth/me')->assertUnauthorized();
});

it('cuts off a session when the staff member is deactivated', function (): void {
    [, $cookie] = signInFromBrowser($this->church, '+233244123456');

    $this->user->update(['is_active' => false]);

    asBrowser($cookie)->getJson('http://grace.localhost/api/v1/auth/me')->assertUnauthorized();
});

it('cuts off a session when the phone number changes', function (): void {
    [, $cookie] = signInFromBrowser($this->church, '+233244123456');

    $this->user->update(['phone' => '+233200111222']);

    asBrowser($cookie)->getJson('http://grace.localhost/api/v1/auth/me')->assertUnauthorized();
});

it('does not honour a church B session on church A', function (): void {
    $other = Church::factory()->create(['slug' => 'other']);
    User::factory()->for($other)->create(['phone' => '+233244999888']);
    [, $cookieB] = signInFromBrowser($other, '+233244999888', 'other');

    asBrowser($cookieB, 'grace')->getJson('http://grace.localhost/api/v1/auth/me')->assertUnauthorized();
});

it('issues a bearer token to a client that is not the browser app', function (): void {
    Cache::put("church:{$this->church->id}:otp:".hash('sha256', '+233244123456'), Hash::make('123456'), 600);

    $this->postJson('http://grace.localhost/api/v1/auth/otp/verify', ['phone' => '+233244123456', 'otp' => '123456'])
        ->assertOk()
        ->assertJsonStructure(['token']);
});

it('does not treat a request from another origin as the app', function (): void {
    Cache::put("church:{$this->church->id}:otp:".hash('sha256', '+233244123456'), Hash::make('123456'), 600);

    $response = $this->withHeaders(['Origin' => 'http://evil.example', 'Accept' => 'application/json'])
        ->postJson('http://grace.localhost/api/v1/auth/otp/verify', ['phone' => '+233244123456', 'otp' => '123456']);

    $response->assertOk()->assertJsonStructure(['token']);
    expect($response->headers->getCookies())->toBeEmpty();
});
