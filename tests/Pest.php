<?php

declare(strict_types=1);

use App\Enums\MemberStatus;
use App\Models\Church;
use App\Models\Department;
use App\Models\Family;
use App\Models\Member;
use App\Models\MemberDepartment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Unit');

// Concurrency tests must really commit, so two connections can contend. They clean up by truncating instead of rolling back.
pest()->extend(TestCase::class)->use(DatabaseTruncation::class)->in('Concurrency');

/*
|--------------------------------------------------------------------------
| Unit test helpers
|--------------------------------------------------------------------------
| Unit tests use mocked repositories and never touch the database (docs: Laravel addendum, Testing).
*/

/** A staff member held in memory. $canDo answers every permission question, for example milestones.view. */
function unitUser(bool $canDo = true): User
{
    Gate::before(fn () => $canDo);

    return User::factory()->make(['id' => '01userunit', 'church_id' => '01churchunit', 'name' => 'Akosua Mensah']);
}

/** A member held in memory. A persisted one has its original values synced, so changes can be detected. */
function unitMember(array $attributes = [], bool $persisted = false): Member
{
    $member = Member::factory()->make([
        'id' => '01memberunit',
        'church_id' => '01churchunit',
        'created_by' => '01userunit',
        'status' => MemberStatus::Active,
        ...$attributes,
    ]);

    if ($persisted) {
        $member->exists = true;
        $member->syncOriginal();
    }

    return $member;
}

function unitChurch(?string $prefix = 'GRC', int $sequence = 0): Church
{
    return Church::factory()->make(['id' => '01churchunit', 'member_number_prefix' => $prefix, 'member_number_seq' => $sequence]);
}

function unitFamily(): Family
{
    return Family::factory()->make(['id' => '01familyunit', 'church_id' => '01churchunit', 'name' => 'Mensah Family']);
}

function unitDepartment(): Department
{
    return Department::factory()->make(['id' => '01deptunit', 'church_id' => '01churchunit']);
}

function unitLink(string $departmentId = '01deptunit'): MemberDepartment
{
    return MemberDepartment::factory()->make([
        'id' => 'link-'.$departmentId,
        'church_id' => '01churchunit',
        'member_id' => '01memberunit',
        'department_id' => $departmentId,
    ]);
}

/** Runs DB::transaction callbacks straight through, so an Action can be tested without a database. */
function withoutDatabaseTransactions(): void
{
    DB::shouldReceive('transaction')->andReturnUsing(fn (Closure $callback) => $callback());
}

/*
|--------------------------------------------------------------------------
| WhatsApp webhook helpers
|--------------------------------------------------------------------------
| Shared by the webhook and CHECKIN tests: a payload as Meta sends it, and a post signed the way Meta signs it.
*/

const WHATSAPP_WEBHOOK = 'http://localhost/api/v1/webhooks/whatsapp';

/** A payload as Meta sends it, holding one text message. */
function metaPayload(string $text = 'CHECKIN', string $from = '233244123456', string $id = 'wamid.ONE', string $type = 'text'): array
{
    $message = ['from' => $from, 'id' => $id, 'timestamp' => '1761383000', 'type' => $type];
    $message[$type] = ['body' => $text];

    return ['object' => 'whatsapp_business_account', 'entry' => [['id' => '1', 'changes' => [['field' => 'messages', 'value' => ['messaging_product' => 'whatsapp', 'messages' => [$message]]]]]]];
}

/** Posts a payload the way Meta does: raw JSON with an HMAC-SHA256 signature of the body. */
function metaPost(array|string $payload, ?string $signature = null): TestResponse
{
    $body = is_array($payload) ? json_encode($payload) : $payload;
    $signature ??= 'sha256='.hash_hmac('sha256', $body, 'app-secret');

    return test()->call('POST', WHATSAPP_WEBHOOK, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => $signature], $body);
}
