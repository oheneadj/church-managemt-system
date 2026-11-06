<?php

declare(strict_types=1);

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Models\Church;
use App\Models\MessageLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

// The message log (docs/11 story 5.3, docs/08 Get Message Log, D-055 point 4, QA-10): what the church has sent, by text,
// WhatsApp and email. It holds phone numbers and message text, so only roles with `sms.send` (church admin, pastor) read it.

const MESSAGES = 'http://grace.localhost/api/v1/messages';

/** A staff member of the church with one role. */
function messageStaff(Church $church, string $role): User
{
    $user = User::factory()->for($church)->create();
    $user->assignRole($role);

    return $user;
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace']);
    $this->other = Church::factory()->create(['slug' => 'other']);
    $this->admin = messageStaff($this->church, 'church-admin');
    Sanctum::actingAs($this->admin, ['*']);
});

describe('listing', function (): void {
    it('lists the church\'s messages newest first, with what a person needs to see', function (): void {
        $old = MessageLog::factory()->create(['church_id' => $this->church->id, 'message_type' => 'receipt', 'content' => 'Thank you for your gift.', 'created_at' => now()->subDay()]);
        $new = MessageLog::factory()->create([
            'church_id' => $this->church->id, 'channel' => MessageChannel::Whatsapp, 'recipient_phone' => '+233244123456', 'recipient_id' => '01HZZZZZZZZZZZZZZZZZZZZZZZ',
            'message_type' => 'checkin_reply', 'content' => 'You are checked in.', 'status' => MessageStatus::Sent, 'sent_at' => now(), 'cost_units' => '1.0000',
            'provider_reference' => 'secret-ref', 'provider_response' => ['request_id' => 'abc'],
        ]);

        $response = $this->getJson(MESSAGES)->assertOk();

        expect(collect($response->json('data'))->pluck('id')->all())->toBe([$new->id, $old->id])
            ->and($response->json('data.0'))->toMatchArray([
                'id' => $new->id, 'channel' => 'whatsapp', 'channel_label' => 'WhatsApp', 'recipient_type' => 'member', 'recipient_id' => '01HZZZZZZZZZZZZZZZZZZZZZZZ',
                'recipient_phone' => '+233244123456', 'recipient_email' => null, 'message_type' => 'checkin_reply', 'content' => 'You are checked in.',
                'status' => 'sent', 'status_label' => 'Sent', 'cost_units' => '1.0000',
            ])
            ->and($response->json('data.0'))->toHaveKeys(['sent_at', 'created_at']);
    });

    it('never shows what only the platform needs: the provider\'s reference and answer', function (): void {
        MessageLog::factory()->create(['church_id' => $this->church->id, 'provider_reference' => 'secret-ref', 'provider_response' => ['request_id' => 'abc']]);

        $body = $this->getJson(MESSAGES)->assertOk()->getContent();

        expect($body)->not->toContain('secret-ref')->not->toContain('provider');
    });

    it('keeps each church\'s messages to itself, and the sign-up code that has no church (isolation)', function (): void {
        MessageLog::factory()->create(['church_id' => $this->church->id, 'content' => 'Mine']);
        MessageLog::factory()->create(['church_id' => $this->other->id, 'content' => 'Theirs']);
        MessageLog::factory()->create(['church_id' => null, 'message_type' => 'broadcast', 'content' => 'Platform']);

        $response = $this->getJson(MESSAGES)->assertOk();

        expect($response->json('meta.total'))->toBe(1)->and($response->getContent())->not->toContain('Theirs')->not->toContain('Platform');
    });

    it('leaves out the codes sent for signing in, which carry nothing to read and only expose contact details', function (): void {
        foreach (['sign_in_code', 'password_reset_code', 'staff_invitation_code', 'password_breached', 'checkin_otp', 'otp'] as $type) {
            MessageLog::factory()->create(['church_id' => $this->church->id, 'message_type' => $type]);
        }
        MessageLog::factory()->create(['church_id' => $this->church->id, 'message_type' => 'receipt']);

        $types = collect($this->getJson(MESSAGES)->assertOk()->json('data'))->pluck('message_type')->all();

        expect($types)->toBe(['receipt']);
    });

    it('takes the list of left-out types from the configuration', function (): void {
        config(['messages.hidden_types' => ['receipt']]);
        MessageLog::factory()->create(['church_id' => $this->church->id, 'message_type' => 'receipt']);
        MessageLog::factory()->create(['church_id' => $this->church->id, 'message_type' => 'sign_in_code']);

        expect(collect($this->getJson(MESSAGES)->assertOk()->json('data'))->pluck('message_type')->all())->toBe(['sign_in_code']);
    });

    it('is paged', function (): void {
        MessageLog::factory()->count(3)->create(['church_id' => $this->church->id]);

        $response = $this->getJson(MESSAGES.'?per_page=2')->assertOk();

        expect($response->json('data'))->toHaveCount(2)->and($response->json('meta.total'))->toBe(3);
        $this->getJson(MESSAGES.'?per_page=1000')->assertStatus(422)->assertJsonValidationErrors('per_page');
    });
});

describe('filters', function (): void {
    it('filters by channel and by type', function (): void {
        MessageLog::factory()->create(['church_id' => $this->church->id, 'channel' => MessageChannel::Sms, 'message_type' => 'receipt']);
        MessageLog::factory()->create(['church_id' => $this->church->id, 'channel' => MessageChannel::Whatsapp, 'message_type' => 'checkin_reply']);
        MessageLog::factory()->create(['church_id' => $this->church->id, 'channel' => MessageChannel::Sms, 'message_type' => 'broadcast']);

        expect($this->getJson(MESSAGES.'?channel=whatsapp')->assertOk()->json('meta.total'))->toBe(1)
            ->and($this->getJson(MESSAGES.'?channel=sms')->assertOk()->json('meta.total'))->toBe(2)
            ->and($this->getJson(MESSAGES.'?message_type=broadcast')->assertOk()->json('meta.total'))->toBe(1)
            ->and($this->getJson(MESSAGES.'?channel=sms&message_type=receipt')->assertOk()->json('meta.total'))->toBe(1);
    });

    it('filters by date, from and to, whole days', function (): void {
        MessageLog::factory()->create(['church_id' => $this->church->id, 'created_at' => '2026-10-01 00:00:00']);
        MessageLog::factory()->create(['church_id' => $this->church->id, 'created_at' => '2026-10-15 23:59:00']);
        MessageLog::factory()->create(['church_id' => $this->church->id, 'created_at' => '2026-11-02 10:00:00']);

        expect($this->getJson(MESSAGES.'?from=2026-10-02')->assertOk()->json('meta.total'))->toBe(2)
            ->and($this->getJson(MESSAGES.'?to=2026-10-15')->assertOk()->json('meta.total'))->toBe(2)
            ->and($this->getJson(MESSAGES.'?from=2026-10-02&to=2026-10-15')->assertOk()->json('meta.total'))->toBe(1);
    });

    it('refuses a channel that does not exist, a bad date and a range that ends before it starts', function (): void {
        $this->getJson(MESSAGES.'?channel=pigeon')->assertStatus(422)->assertJsonValidationErrors('channel');
        $this->getJson(MESSAGES.'?from=yesterday')->assertStatus(422)->assertJsonValidationErrors('from');
        $this->getJson(MESSAGES.'?from=2026-10-15&to=2026-10-01')->assertStatus(422)->assertJsonValidationErrors('to');
    });
});

describe('who may read it', function (): void {
    it('refuses a visitor with no sign-in', function (): void {
        $this->app['auth']->forgetGuards();

        $this->getJson(MESSAGES)->assertUnauthorized();
    });

    it('lets the church admin and the pastor read it', function (string $role): void {
        Sanctum::actingAs(messageStaff($this->church, $role), ['*']);

        $this->getJson(MESSAGES)->assertOk();
    })->with(['church-admin', 'pastor']);

    it('refuses the roles without sms.send', function (string $role): void {
        MessageLog::factory()->create(['church_id' => $this->church->id]);
        Sanctum::actingAs(messageStaff($this->church, $role), ['*']);

        $this->getJson(MESSAGES)->assertForbidden()->assertJsonPath('message', 'This action is unauthorized.');
    })->with(['finance-secretary', 'department-head', 'youth-pastor']);
});
