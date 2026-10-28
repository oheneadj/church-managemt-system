<?php

declare(strict_types=1);

use App\Models\Church;
use App\Models\Contribution;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Gate;

// The church scope already hides another church's gifts, so the policy's own church check is a second lock
// (docs/10, Security). The HTTP tests never reach it, so it is tested here by asking the policy directly.

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $church = Church::factory()->create();
    $this->admin = User::factory()->for($church)->create();
    $this->admin->assignRole('church-admin');
    $this->own = Contribution::factory()->create(['church_id' => $church->id]);
    $this->foreign = Contribution::factory()->create();
});

it('lets staff see and void gifts of their own church only', function (string $ability): void {
    expect(Gate::forUser($this->admin)->allows($ability, $this->own))->toBeTrue()
        ->and(Gate::forUser($this->admin)->allows($ability, $this->foreign))->toBeFalse();
})->with(['view', 'void']);

it('does not let the pastor see or list gifts', function (): void {
    $pastor = User::factory()->for($this->own->church)->create();
    $pastor->assignRole('pastor');

    expect(Gate::forUser($pastor)->allows('viewAny', Contribution::class))->toBeFalse()
        ->and(Gate::forUser($pastor)->allows('view', $this->own))->toBeFalse()
        ->and(Gate::forUser($pastor)->allows('create', Contribution::class))->toBeFalse();
});
