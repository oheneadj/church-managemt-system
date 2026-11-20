<?php

declare(strict_types=1);

use App\Models\Church;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Queue;

// The support runbook (docs/16): lift one email's sign-in lockout after confirming with the church admin. It replaces
// the phone-number command that went with the old SMS sign-in (D-054). The helpers are in StaffLoginTest.php.

beforeEach(function (): void {
    Queue::fake();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace']);
    $this->user = User::factory()->for($this->church)->create(['email' => 'akosua@example.com']);
    $this->user->forceFill(['password' => STAFF_PASSWORD])->save();
});

it('lets the person try again after five wrong passwords locked the email', function (): void {
    foreach (range(1, 5) as $ignored) {
        staffPassword(['password' => 'wrong']);
    }
    staffPassword()->assertStatus(429);

    $this->artisan('staff:reset-lockout', ['church' => 'grace', 'email' => 'Akosua@Example.com'])->assertSuccessful();

    staffPassword()->assertOk();
});

it('lets the person try again after five wrong codes, with a fresh code', function (): void {
    staffPassword()->assertOk();
    $old = emailedCode();
    foreach (range(1, 5) as $ignored) {
        staffCode('000000');
    }
    staffCode($old)->assertStatus(429);

    $this->artisan('staff:reset-lockout', ['church' => 'grace', 'email' => 'akosua@example.com'])->assertSuccessful();

    // The pending code is gone too, so the old one no longer works and a new one is asked for.
    staffCode($old)->assertStatus(422);
    $this->travel(2)->minutes(); // asking for another code is still limited to one a minute
    staffPassword()->assertOk();
    staffCode(emailedCode())->assertOk();
});

it('leaves another email locked', function (): void {
    $other = User::factory()->for($this->church)->create(['email' => 'other@example.com']);
    $other->forceFill(['password' => STAFF_PASSWORD])->save();
    foreach (range(1, 5) as $ignored) {
        staffPassword(['email' => 'other@example.com', 'password' => 'wrong']);
    }

    $this->artisan('staff:reset-lockout', ['church' => 'grace', 'email' => 'akosua@example.com'])->assertSuccessful();

    staffPassword(['email' => 'other@example.com'])->assertStatus(429);
});

it('refuses an unknown church and an invalid email', function (): void {
    $this->artisan('staff:reset-lockout', ['church' => 'nowhere', 'email' => 'akosua@example.com'])->assertFailed();
    $this->artisan('staff:reset-lockout', ['church' => 'grace', 'email' => 'not-an-email'])->assertFailed();
});
