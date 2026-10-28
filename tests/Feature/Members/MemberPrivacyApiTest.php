<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Enums\MemberStatus;
use App\Models\AuditLog;
use App\Models\Church;
use App\Models\Family;
use App\Models\Member;
use App\Models\MemberMilestone;
use App\Models\MessageLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

// Subject access and erasure for a member (agreement section 36, docs/05). Export lists everything held about a
// person; erasure removes their personal details everywhere but keeps the record, soft-deleted and archived.

const PRIVACY_MEMBERS = 'http://grace.localhost/api/v1/members';

/** A staff member of the church with one role. */
function privacyStaff(Church $church, string $role): User
{
    $user = User::factory()->for($church)->create(['name' => 'Staff '.$role]);
    $user->assignRole($role);

    return $user;
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace']);
    $this->admin = privacyStaff($this->church, 'church-admin');
    $this->member = Member::factory()->create([
        'church_id' => $this->church->id,
        'first_name' => 'Abena',
        'last_name' => 'Asante',
        'phone' => '+233200987654',
        'email' => 'abena@example.com',
        'address' => '12 Ring Road, Accra',
        'national_id_number' => 'GHA-123456789-0',
        'occupation' => 'Nurse',
        'photo_url' => 'churches/x/members/y/photo.jpg',
        'status' => MemberStatus::Active,
    ]);
});

describe('export', function (): void {
    it('lists everything held about the member, with the decrypted fields', function (): void {
        $family = Family::factory()->create(['church_id' => $this->church->id, 'name' => 'Asante Family']);
        $this->member->update(['family_id' => $family->id, 'family_role' => 'spouse']);
        MemberMilestone::factory()->create(['church_id' => $this->church->id, 'member_id' => $this->member->id, 'notes' => 'Baptised with her sister']);

        Sanctum::actingAs($this->admin, ['*']);
        $response = $this->getJson(PRIVACY_MEMBERS.'/'.$this->member->id.'/data-export')->assertOk();

        $response->assertJsonPath('member.id', $this->member->id)
            ->assertJsonPath('member.first_name', 'Abena')
            ->assertJsonPath('member.address', '12 Ring Road, Accra')
            ->assertJsonPath('member.national_id_number', 'GHA-123456789-0')
            ->assertJsonPath('member.email', 'abena@example.com')
            ->assertJsonPath('family.name', 'Asante Family')
            ->assertJsonPath('milestones.0.notes', 'Baptised with her sister')
            ->assertJsonStructure(['exported_at', 'member', 'family', 'departments', 'milestones', 'consents', 'history']);
    });

    it('never exposes an integer key', function (): void {
        Sanctum::actingAs($this->admin, ['*']);

        $json = $this->getJson(PRIVACY_MEMBERS.'/'.$this->member->id.'/data-export')->assertOk()->json();

        expect($json['member']['id'])->toMatch('/^[0-9a-z]{26}$/');
    });

    it('records who exported the data, without the data', function (): void {
        Sanctum::actingAs($this->admin, ['*']);
        $this->getJson(PRIVACY_MEMBERS.'/'.$this->member->id.'/data-export')->assertOk();

        $line = AuditLog::query()->where('auditable_id', $this->member->id)->where('event', AuditEvent::Exported)->sole();
        expect($line->actor_id)->toBe($this->admin->id)->and($line->new_values)->toBeNull()->and($line->old_values)->toBeNull();
    });

    it('requires a token', function (): void {
        $this->getJson(PRIVACY_MEMBERS.'/'.$this->member->id.'/data-export')->assertUnauthorized();
    });

    it('is forbidden to roles without the export permission', function (string $role): void {
        Sanctum::actingAs(privacyStaff($this->church, $role), ['*']);

        $this->getJson(PRIVACY_MEMBERS.'/'.$this->member->id.'/data-export')->assertForbidden();
    })->with(['department-head', 'youth-pastor']);

    it('returns 404 for another church\'s member', function (): void {
        $other = Church::factory()->create(['slug' => 'other']);
        $stranger = Member::factory()->create(['church_id' => $other->id]);
        Sanctum::actingAs($this->admin, ['*']);

        $this->getJson(PRIVACY_MEMBERS.'/'.$stranger->id.'/data-export')->assertNotFound();
        expect(AuditLog::query()->withoutGlobalScopes()->where('event', AuditEvent::Exported)->count())->toBe(0);
    });
});

describe('erasure', function (): void {
    it('empties the personal details, archives and soft-deletes the member, and answers 204', function (): void {
        Sanctum::actingAs($this->admin, ['*']);

        $this->postJson(PRIVACY_MEMBERS.'/'.$this->member->id.'/erasure', ['reason' => 'Member asked in writing on 2026-10-20'])->assertNoContent();

        $erased = Member::query()->withTrashed()->findOrFail($this->member->id);
        expect($erased->trashed())->toBeTrue()
            ->and($erased->first_name)->toBe('[deleted]')
            ->and($erased->last_name)->toBe('[deleted]')
            ->and($erased->phone)->toBe('[deleted]')
            ->and($erased->email)->toBeNull()
            ->and($erased->address)->toBeNull()
            ->and($erased->national_id_number)->toBeNull()
            ->and($erased->occupation)->toBeNull()
            ->and($erased->photo_url)->toBeNull()
            ->and($erased->status)->toBe(MemberStatus::Archived)
            ->and($erased->sms_opted_in)->toBeFalse()
            ->and($erased->member_number)->toBe($this->member->member_number);
    });

    it('deletes the stored photo file', function (): void {
        Storage::fake();
        Storage::put('churches/x/members/y/photo.jpg', 'bytes');
        Sanctum::actingAs($this->admin, ['*']);

        $this->postJson(PRIVACY_MEMBERS.'/'.$this->member->id.'/erasure', ['reason' => 'Member request'])->assertNoContent();

        Storage::assertMissing('churches/x/members/y/photo.jpg');
    });

    it('hides the member from the directory and the member endpoint', function (): void {
        Sanctum::actingAs($this->admin, ['*']);
        $this->postJson(PRIVACY_MEMBERS.'/'.$this->member->id.'/erasure', ['reason' => 'Member request'])->assertNoContent();

        $this->getJson(PRIVACY_MEMBERS.'/'.$this->member->id)->assertNotFound();
        $this->getJson(PRIVACY_MEMBERS.'?search=Abena')->assertOk()->assertJsonPath('meta.total', 0);
    });

    it('frees the phone number for a new member', function (): void {
        Sanctum::actingAs($this->admin, ['*']);
        $this->postJson(PRIVACY_MEMBERS.'/'.$this->member->id.'/erasure', ['reason' => 'Member request'])->assertNoContent();

        $this->postJson(PRIVACY_MEMBERS, ['first_name' => 'New', 'last_name' => 'Person', 'gender' => 'female', 'phone' => '0200987654'])->assertCreated();
    });

    it('masks the values in the member\'s history but keeps every line and field name', function (): void {
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson(PRIVACY_MEMBERS.'/'.$this->member->id, ['occupation' => 'Teacher'])->assertOk();
        $before = AuditLog::query()->where('auditable_id', $this->member->id)->count();

        $this->postJson(PRIVACY_MEMBERS.'/'.$this->member->id.'/erasure', ['reason' => 'Member request'])->assertNoContent();

        $lines = AuditLog::query()->where('auditable_id', $this->member->id)->get();
        $updated = $lines->firstWhere('event', AuditEvent::Updated);
        expect($lines)->toHaveCount($before + 1)
            ->and($updated->old_values)->toBe(['occupation' => '[deleted]'])
            ->and($updated->new_values)->toBe(['occupation' => '[deleted]'])
            ->and($updated->changed_fields)->toBe(['occupation'])
            ->and(json_encode($lines->pluck('new_values')))->not->toContain('Teacher');
    });

    it('records the erasure with its reason and the admin, and no personal value', function (): void {
        Sanctum::actingAs($this->admin, ['*']);
        $this->postJson(PRIVACY_MEMBERS.'/'.$this->member->id.'/erasure', ['reason' => 'Member request'])->assertNoContent();

        $line = AuditLog::query()->where('auditable_id', $this->member->id)->where('event', AuditEvent::Erased)->sole();
        expect($line->reason)->toBe('Member request')->and($line->actor_id)->toBe($this->admin->id)->and($line->new_values)->toBeNull();
    });

    it('removes the phone and text from messages sent to the member but keeps the log lines', function (): void {
        $message = MessageLog::factory()->create([
            'church_id' => $this->church->id, 'recipient_id' => $this->member->id, 'recipient_phone' => '+233200987654', 'content' => 'Hello Abena',
        ]);
        $unrelated = MessageLog::factory()->create(['church_id' => $this->church->id, 'recipient_phone' => '+233244000000', 'content' => 'Hello Kofi']);
        Sanctum::actingAs($this->admin, ['*']);

        $this->postJson(PRIVACY_MEMBERS.'/'.$this->member->id.'/erasure', ['reason' => 'Member request'])->assertNoContent();

        expect($message->fresh()->recipient_phone)->toBeNull()
            ->and($message->fresh()->content)->toBe('[deleted]')
            ->and($unrelated->fresh()->content)->toBe('Hello Kofi');
    });

    it('clears milestone notes and releases a family headed by the member', function (): void {
        $family = Family::factory()->create(['church_id' => $this->church->id, 'head_member_id' => $this->member->id]);
        $milestone = MemberMilestone::factory()->create(['church_id' => $this->church->id, 'member_id' => $this->member->id, 'notes' => 'With her sister Ama']);
        Sanctum::actingAs($this->admin, ['*']);

        $this->postJson(PRIVACY_MEMBERS.'/'.$this->member->id.'/erasure', ['reason' => 'Member request'])->assertNoContent();

        expect($milestone->fresh()->notes)->toBeNull()->and($family->fresh()->head_member_id)->toBeNull();
    });

    it('leaves other members untouched', function (): void {
        $other = Member::factory()->create(['church_id' => $this->church->id, 'first_name' => 'Kofi', 'occupation' => 'Driver']);
        Sanctum::actingAs($this->admin, ['*']);

        $this->postJson(PRIVACY_MEMBERS.'/'.$this->member->id.'/erasure', ['reason' => 'Member request'])->assertNoContent();

        expect($other->fresh()->first_name)->toBe('Kofi')->and($other->fresh()->occupation)->toBe('Driver');
    });

    it('needs a reason', function (array $body): void {
        Sanctum::actingAs($this->admin, ['*']);

        $this->postJson(PRIVACY_MEMBERS.'/'.$this->member->id.'/erasure', $body)->assertUnprocessable()->assertJsonValidationErrors('reason');
        expect($this->member->fresh()->first_name)->toBe('Abena');
    })->with([[[]], [['reason' => '']], [['reason' => 'ab']]]);

    it('requires a token', function (): void {
        $this->postJson(PRIVACY_MEMBERS.'/'.$this->member->id.'/erasure', ['reason' => 'Member request'])->assertUnauthorized();
    });

    it('is forbidden to every role except the church admin', function (string $role): void {
        Sanctum::actingAs(privacyStaff($this->church, $role), ['*']);

        $this->postJson(PRIVACY_MEMBERS.'/'.$this->member->id.'/erasure', ['reason' => 'Member request'])->assertForbidden();
        expect($this->member->fresh()->first_name)->toBe('Abena');
    })->with(['pastor', 'finance-secretary', 'department-head', 'youth-pastor']);

    it('returns 404 for another church\'s member and changes nothing', function (): void {
        $other = Church::factory()->create(['slug' => 'other']);
        $stranger = Member::factory()->create(['church_id' => $other->id, 'first_name' => 'Yaw']);
        Sanctum::actingAs($this->admin, ['*']);

        $this->postJson(PRIVACY_MEMBERS.'/'.$stranger->id.'/erasure', ['reason' => 'Member request'])->assertNotFound();
        expect(Member::query()->withoutGlobalScopes()->find($stranger->id)->first_name)->toBe('Yaw');
    });

    it('cannot be repeated: the erased member is gone', function (): void {
        Sanctum::actingAs($this->admin, ['*']);
        $this->postJson(PRIVACY_MEMBERS.'/'.$this->member->id.'/erasure', ['reason' => 'Member request'])->assertNoContent();

        $this->postJson(PRIVACY_MEMBERS.'/'.$this->member->id.'/erasure', ['reason' => 'Again please'])->assertNotFound();
    });
});
