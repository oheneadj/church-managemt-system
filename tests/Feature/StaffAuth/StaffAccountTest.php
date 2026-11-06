<?php

declare(strict_types=1);

use App\Models\Church;
use App\Models\User;
use App\Repositories\UserRepositoryInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;

// D-054: a staff member is known by their email and signs in with a password. These are the data rules under that.

beforeEach(function (): void {
    $this->church = Church::factory()->create(['slug' => 'grace']);
    app()->instance('current.church', $this->church);
});

it('stores a password only as a hash and never shows it', function (): void {
    $user = User::factory()->for($this->church)->create();

    $user->forceFill(['password' => 'a long passphrase here'])->save();
    $user->refresh();

    expect($user->password)->not->toBe('a long passphrase here')
        ->and(Hash::check('a long passphrase here', $user->password))->toBeTrue()
        ->and($user->toArray())->not->toHaveKey('password')
        ->and(json_encode($user))->not->toContain('a long passphrase here');
});

it('starts with no password, so a person who has none sets one through the reset flow', function (): void {
    $user = User::factory()->for($this->church)->create();

    expect($user->password)->toBeNull()->and($user->email_verified_at)->toBeNull();
});

it('finds a staff member by email ignoring capitals and spaces around it', function (): void {
    $user = User::factory()->for($this->church)->create(['email' => 'Akosua.Mensah@Example.com']);

    $found = app(UserRepositoryInterface::class)->findByEmail('  akosua.mensah@example.COM ');

    expect($found?->is($user))->toBeTrue();
});

it('never finds a staff member of another church', function (): void {
    $other = Church::factory()->create();
    User::factory()->for($other)->create(['email' => 'akosua@example.com']);

    expect(app(UserRepositoryInterface::class)->findByEmail('akosua@example.com'))->toBeNull();
});

it('does not find a deleted staff member', function (): void {
    $user = User::factory()->for($this->church)->create(['email' => 'akosua@example.com']);
    $user->delete();

    expect(app(UserRepositoryInterface::class)->findByEmail('akosua@example.com'))->toBeNull();
});

it('refuses two staff members of one church with the same email, whatever the capitals', function (): void {
    User::factory()->for($this->church)->create(['email' => 'akosua@example.com']);

    expect(fn () => User::factory()->for($this->church)->create(['email' => 'AKOSUA@example.com']))->toThrow(QueryException::class);
});

it('lets two churches use the same email, and lets a deleted staff member free theirs', function (): void {
    $other = Church::factory()->create();
    $first = User::factory()->for($this->church)->create(['email' => 'akosua@example.com']);
    User::factory()->for($other)->create(['email' => 'akosua@example.com']);

    $first->delete();
    User::factory()->for($this->church)->create(['email' => 'akosua@example.com']);

    expect(User::query()->withoutGlobalScopes()->where('email', 'akosua@example.com')->whereNull('deleted_at')->count())->toBe(2);
});

it('allows staff without an email for now, until every account has one', function (): void {
    User::factory()->for($this->church)->count(2)->create(['email' => null]);

    expect(User::query()->where('church_id', $this->church->id)->whereNull('email')->count())->toBe(2);
});
