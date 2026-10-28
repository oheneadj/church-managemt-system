<?php

declare(strict_types=1);

use App\Models\Church;
use App\Models\FundType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Gate;

// The church scope already hides another church's fund types, so the policy's own church check is a second lock
// (docs/10, Security). The HTTP tests never reach it, so it is tested here by asking the policy directly.

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $church = Church::factory()->create();
    $this->admin = User::factory()->for($church)->create();
    $this->admin->assignRole('church-admin');
    $this->own = FundType::factory()->create(['church_id' => $church->id]);
    $this->foreign = FundType::factory()->create();
});

it('refuses to change a fund type of another church even for an admin', function (): void {
    expect(Gate::forUser($this->admin)->allows('update', $this->own))->toBeTrue()
        ->and(Gate::forUser($this->admin)->allows('update', $this->foreign))->toBeFalse();
});
