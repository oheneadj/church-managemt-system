<?php

declare(strict_types=1);

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Unit test helpers
|--------------------------------------------------------------------------
| Unit tests use mocked repositories and never touch the database (docs: Laravel addendum, Testing).
*/

/** A staff member held in memory. $canDo answers every permission question, for example milestones.view. */
function unitUser(bool $canDo = true): User
{
    $user = new class extends User
    {
        public bool $canDo = true;

        public function can($abilities, $arguments = []): bool
        {
            return $this->canDo;
        }
    };
    $user->canDo = $canDo;
    $user->forceFill(['id' => '01userunit', 'church_id' => '01churchunit', 'name' => 'Akosua Mensah']);

    return $user;
}

/** A member held in memory. A persisted one has its original values synced, so changes can be detected. */
function unitMember(array $attributes = [], bool $persisted = false): Member
{
    $member = new Member;
    $member->forceFill(['id' => '01memberunit', 'church_id' => '01churchunit', 'status' => MemberStatus::Active, ...$attributes]);

    if ($persisted) {
        $member->exists = true;
        $member->syncOriginal();
    }

    return $member;
}

/** Runs DB::transaction callbacks straight through, so an Action can be tested without a database. */
function withoutDatabaseTransactions(): void
{
    DB::shouldReceive('transaction')->andReturnUsing(fn (Closure $callback) => $callback());
}
