<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\Church;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

// Staff management, part 2 (D-055 point 7, docs/08 Staff, QA-10): deactivate or reactivate a staff member, and grant or
// remove single permissions on top of their role. The helpers and the STAFF address are in StaffApiTest.php.

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace']);
    $this->admin = staffOf($this->church, 'church-admin', ['name' => 'Kofi Admin']);
    $this->other = Church::factory()->create(['slug' => 'other']);
    $this->pastor = staffOf($this->church, 'pastor', ['name' => 'Abena Pastor']);
    Sanctum::actingAs($this->admin, ['*']);
});

describe('deactivating and reactivating', function (): void {
    it('deactivates a staff member and says so', function (): void {
        $this->patchJson(STAFF.'/'.$this->pastor->id.'/status', ['is_active' => false])->assertOk()
            ->assertJsonPath('data.is_active', false)->assertJsonPath('data.id', $this->pastor->id);

        expect($this->pastor->fresh()->is_active)->toBeFalse();
    });

    it('ends the person\'s tokens at once, so their next request is refused', function (): void {
        $token = $this->pastor->createToken('phone')->plainTextToken;
        app('auth')->forgetGuards();
        $this->withToken($token)->getJson('http://grace.localhost/api/v1/auth/me')->assertOk();

        $this->flushHeaders();
        Sanctum::actingAs($this->admin, ['*']);
        $this->patchJson(STAFF.'/'.$this->pastor->id.'/status', ['is_active' => false])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('http://grace.localhost/api/v1/auth/me')->assertUnauthorized();
        expect($this->pastor->tokens()->count())->toBe(0);
    });

    it('reactivates a staff member', function (): void {
        $this->pastor->update(['is_active' => false]);

        $this->patchJson(STAFF.'/'.$this->pastor->id.'/status', ['is_active' => true])->assertOk()->assertJsonPath('data.is_active', true);

        expect($this->pastor->fresh()->is_active)->toBeTrue();
    });

    it('writes the change to the history, and nothing when the state is already that', function (): void {
        $this->patchJson(STAFF.'/'.$this->pastor->id.'/status', ['is_active' => false])->assertOk();
        $this->patchJson(STAFF.'/'.$this->pastor->id.'/status', ['is_active' => false])->assertOk();

        $line = AuditLog::query()->where('auditable_id', $this->pastor->id)->sole();
        expect($line->event)->toBe(AuditEvent::StatusChanged)->and($line->actor_id)->toBe($this->admin->id)
            ->and($line->old_values)->toBe(['is_active' => true])->and($line->new_values)->toBe(['is_active' => false]);
    });

    it('asks for a true or false', function (): void {
        $this->patchJson(STAFF.'/'.$this->pastor->id.'/status', [])->assertStatus(422)->assertJsonValidationErrors('is_active');
        $this->patchJson(STAFF.'/'.$this->pastor->id.'/status', ['is_active' => 'maybe'])->assertStatus(422)->assertJsonValidationErrors('is_active');
    });

    it('refuses to deactivate the last active church admin', function (): void {
        $this->patchJson(STAFF.'/'.$this->admin->id.'/status', ['is_active' => false])->assertStatus(409)
            ->assertJsonPath('message', 'A church needs at least one active church admin.');

        expect($this->admin->fresh()->is_active)->toBeTrue();
    });

    it('lets a church admin deactivate another, or themselves while another remains', function (): void {
        $second = staffOf($this->church, 'church-admin');

        $this->patchJson(STAFF.'/'.$second->id.'/status', ['is_active' => false])->assertOk();
        $this->patchJson(STAFF.'/'.$this->admin->id.'/status', ['is_active' => false])->assertStatus(409);
        $second->refresh()->update(['is_active' => true]);
        $this->patchJson(STAFF.'/'.$this->admin->id.'/status', ['is_active' => false])->assertOk();
    });

    it('does not count a church admin of another church as one who remains', function (): void {
        staffOf($this->other, 'church-admin');

        $this->patchJson(STAFF.'/'.$this->admin->id.'/status', ['is_active' => false])->assertStatus(409);
    });
});

describe('extra permissions', function (): void {
    it('grants a permission on top of the role, and it works on the next request', function (): void {
        expect($this->pastor->can('members.export'))->toBeFalse();

        $this->putJson(STAFF.'/'.$this->pastor->id.'/permissions', ['grant' => ['members.export']])->assertOk()
            ->assertJsonPath('data.extra_permissions', ['members.export'])->assertJsonPath('data.roles', ['pastor']);

        expect($this->pastor->fresh()->can('members.export'))->toBeTrue();
    });

    it('removes a permission that was granted, and it stops working', function (): void {
        $this->pastor->givePermissionTo('members.export');

        $this->putJson(STAFF.'/'.$this->pastor->id.'/permissions', ['revoke' => ['members.export']])->assertOk()
            ->assertJsonPath('data.extra_permissions', []);

        expect($this->pastor->fresh()->can('members.export'))->toBeFalse();
    });

    it('grants and removes in one call', function (): void {
        $this->pastor->givePermissionTo('giving.export');

        $this->putJson(STAFF.'/'.$this->pastor->id.'/permissions', ['grant' => ['members.export'], 'revoke' => ['giving.export']])->assertOk()
            ->assertJsonPath('data.extra_permissions', ['members.export']);
    });

    it('does not turn a role permission into an extra one, and says nothing changed', function (): void {
        $this->putJson(STAFF.'/'.$this->pastor->id.'/permissions', ['grant' => ['sms.send']])->assertOk()->assertJsonPath('data.extra_permissions', []);

        expect(AuditLog::query()->where('auditable_id', $this->pastor->id)->count())->toBe(0);
    });

    it('refuses to remove a permission the role gives, and points to the role', function (): void {
        $this->putJson(STAFF.'/'.$this->pastor->id.'/permissions', ['revoke' => ['sms.send']])->assertStatus(422)
            ->assertJsonValidationErrors('revoke.0');

        expect($this->pastor->fresh()->can('sms.send'))->toBeTrue();
    });

    it('treats removing a permission the person never had as nothing to do', function (): void {
        $this->putJson(STAFF.'/'.$this->pastor->id.'/permissions', ['revoke' => ['members.export']])->assertOk();
    });

    it('writes the old and new extras to the history', function (): void {
        $this->putJson(STAFF.'/'.$this->pastor->id.'/permissions', ['grant' => ['members.export', 'giving.export']])->assertOk();
        $this->putJson(STAFF.'/'.$this->pastor->id.'/permissions', ['revoke' => ['giving.export']])->assertOk();

        $lines = AuditLog::query()->where('auditable_id', $this->pastor->id)->orderBy('id')->get();
        expect($lines)->toHaveCount(2)->and($lines[0]->event)->toBe(AuditEvent::Updated)
            ->and($lines[0]->old_values)->toBe(['extra_permissions' => []])
            ->and($lines[0]->new_values)->toBe(['extra_permissions' => ['giving.export', 'members.export']])
            ->and($lines[1]->old_values)->toBe(['extra_permissions' => ['giving.export', 'members.export']])
            ->and($lines[1]->new_values)->toBe(['extra_permissions' => ['members.export']]);
    });

    it('refuses an unknown permission, a bad shape, an empty call and a permission in both lists', function (): void {
        $this->putJson(STAFF.'/'.$this->pastor->id.'/permissions', ['grant' => ['make.coffee']])->assertStatus(422)->assertJsonValidationErrors('grant.0');
        $this->putJson(STAFF.'/'.$this->pastor->id.'/permissions', ['grant' => 'members.export'])->assertStatus(422)->assertJsonValidationErrors('grant');
        $this->putJson(STAFF.'/'.$this->pastor->id.'/permissions', [])->assertStatus(422)->assertJsonValidationErrors(['grant', 'revoke']);
        $this->putJson(STAFF.'/'.$this->pastor->id.'/permissions', ['grant' => ['members.export'], 'revoke' => ['members.export']])->assertStatus(422)->assertJsonValidationErrors('revoke.0');
    });

    it('keeps the extras when the role changes', function (): void {
        $this->pastor->givePermissionTo('members.export');

        $this->putJson(STAFF.'/'.$this->pastor->id, ['role' => 'youth-pastor'])->assertOk()->assertJsonPath('data.extra_permissions', ['members.export']);
    });
});

describe('who may do this', function (): void {
    it('refuses a visitor with no sign-in', function (): void {
        $this->app['auth']->forgetGuards();

        $this->patchJson(STAFF.'/'.$this->pastor->id.'/status', ['is_active' => false])->assertUnauthorized();
        $this->putJson(STAFF.'/'.$this->pastor->id.'/permissions', ['grant' => ['members.export']])->assertUnauthorized();
    });

    it('refuses every role except the church admin', function (string $role): void {
        Sanctum::actingAs(staffOf($this->church, $role), ['*']);

        $this->patchJson(STAFF.'/'.$this->pastor->id.'/status', ['is_active' => false])->assertForbidden();
        $this->putJson(STAFF.'/'.$this->pastor->id.'/permissions', ['grant' => ['members.export']])->assertForbidden();
        expect($this->pastor->fresh()->is_active)->toBeTrue()->and($this->pastor->fresh()->can('members.export'))->toBeFalse();
    })->with(['pastor', 'finance-secretary', 'department-head', 'youth-pastor']);

    it('treats a staff member of another church as not found (isolation)', function (): void {
        $stranger = staffOf($this->other, 'pastor');

        $this->patchJson(STAFF.'/'.$stranger->id.'/status', ['is_active' => false])->assertNotFound();
        $this->putJson(STAFF.'/'.$stranger->id.'/permissions', ['grant' => ['members.export']])->assertNotFound();
        expect($stranger->fresh()->is_active)->toBeTrue()->and($stranger->fresh()->can('members.export'))->toBeFalse();
    });
});
